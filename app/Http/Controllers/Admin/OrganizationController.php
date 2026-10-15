<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\OrganizationLogoService;
use App\Services\OrganizationRegistrar;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organizations, managed by the Super Administrator (docs/project.md F1).
 */
class OrganizationController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly OrganizationRegistrar $registrar,
        private readonly OrganizationLogoService $logos,
    ) {}

    /**
     * List the organizations of the platform.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $organizations = Organization::query()
            ->withCount('alumni')
            ->with(['activeSubscription.plan:id,name', 'latestSubscription.plan'])
            ->when($validated['search'] ?? null, function ($query, string $term) {
                $like = '%'.addcslashes($term, '%_').'%';

                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'email' => $organization->email,
                'is_active' => $organization->is_active,
                'logo_url' => $organization->logo_url,
                'alumni_count' => $organization->alumni_count,
                'plan' => $organization->activeSubscription?->plan?->name,
                'ends_at' => $organization->activeSubscription?->ends_at?->toDateString(),
                'trial' => $this->presentTrial($organization),
            ]);

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => (int) ($validated['per_page'] ?? 10),
            ],
        ]);
    }

    /**
     * Show the form for creating an organization and its first administrator.
     */
    public function create(): Response
    {
        return Inertia::render('admin/organizations/create', [
            'plans' => $this->activePlans(),
        ]);
    }

    /**
     * Create an organization, its roles and its first administrator.
     */
    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $organization = DB::transaction(function () use ($request, $validated) {
            ['organization' => $organization] = $this->registrar->register(
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ],
                [
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => $validated['admin_password'],
                ],
            );

            if (($plan = $request->plan()) !== null && $plan->isFree()) {
                $start = Carbon::today();
                $this->subscriptions->assign($organization, $plan, 'monthly', $start, $start->copy()->addDays((int) $validated['trial_days']));
            } elseif ($plan !== null) {
                $this->subscriptions->assign($organization, $plan, $validated['billing_cycle']);
            }

            return $organization;
        });

        if ($request->hasFile('logo')) {
            $this->logos->store($organization, $request->file('logo'));
        }

        return to_route('admin.organizations.index')
            ->with('success', "Organisation {$organization->name} creee.");
    }

    /**
     * Show the form for editing an organization.
     */
    public function edit(Organization $organization): Response
    {
        return Inertia::render('admin/organizations/edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'address' => $organization->address,
                'is_active' => $organization->is_active,
                'logo_url' => $organization->logo_url,
                'trial' => $this->presentTrial($organization),
            ],
            'maxTrialDays' => StoreOrganizationRequest::MAX_TRIAL_DAYS,
        ]);
    }

    /**
     * Update an organization from the submitted form.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->safe()->only(['name', 'email', 'phone', 'address']));

        $this->applyLogo($request, $organization);

        return to_route('admin.organizations.index')
            ->with('success', 'Organisation mise a jour.');
    }

    /**
     * Apply the logo change the form asked for: replace, remove, or leave alone.
     */
    private function applyLogo(UpdateOrganizationRequest $request, Organization $organization): void
    {
        if ($request->hasFile('logo')) {
            $this->logos->store($organization, $request->file('logo'));

            return;
        }

        if ($request->boolean('remove_logo')) {
            $this->logos->remove($organization);
        }
    }

    /**
     * Activate or deactivate an organization (docs/project.md F1).
     */
    public function toggleActive(Organization $organization): RedirectResponse
    {
        $organization->update(['is_active' => ! $organization->is_active]);

        return back()->with(
            'success',
            $organization->is_active ? 'Organisation activee.' : 'Organisation desactivee.',
        );
    }

    /**
     * Extend the organization's free demo trial by a number of days, reopening its space if the trial had ended.
     */
    public function extendTrial(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:'.StoreOrganizationRequest::MAX_TRIAL_DAYS],
        ]);

        $trial = $organization->trial();

        abort_if($trial === null, 404);

        // A running trial grows from its end date; an ended one restarts from today.
        $from = $trial->isActive()
            ? Carbon::parse($trial->ends_at->toDateString())
            : now()->startOfDay();

        $trial->update([
            'status' => 'active',
            'ends_at' => $from->addDays((int) $validated['days']),
            'canceled_at' => null,
        ]);

        return back()->with('success', "Essai prolonge jusqu au {$trial->ends_at->format('d/m/Y')}.");
    }

    /**
     * The organization's demo trial as the admin screens show it, or null when it is not on one.
     *
     * @return array{ends_at: string, ended: bool, days_left: int}|null
     */
    private function presentTrial(Organization $organization): ?array
    {
        $trial = $organization->trial();

        if ($trial === null) {
            return null;
        }

        $endsAt = Carbon::parse($trial->ends_at->toDateString());

        return [
            'ends_at' => $endsAt->toDateString(),
            'ended' => ! $trial->isActive(),
            'days_left' => max(0, (int) now()->startOfDay()->diffInDays($endsAt, false)),
        ];
    }

    /**
     * The plans an organization can be subscribed to, for the form.
     *
     * @return array<int, array{id: int, name: string, price_monthly: string, price_yearly: string, is_free: bool}>
     */
    private function activePlans(): array
    {
        return Plan::query()
            ->offered()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'is_free' => $plan->isFree(),
            ])
            ->values()
            ->all();
    }
}
