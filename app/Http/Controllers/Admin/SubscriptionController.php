<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PricingCalculator;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subscriptions and payment history (docs/project.md F1, F6, F8).
 */
class SubscriptionController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PricingCalculator $pricing,
    ) {}

    /**
     * List the subscriptions.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'active', 'expired', 'canceled'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->subscriptions->expireOverdue();

        $subscriptions = Subscription::withoutGlobalScopes()
            ->with(['organization:id,name', 'plan:id,name'])
            ->withCount(['payments as paid_count' => fn ($query) => $query->where('status', 'paid')])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'organization' => $subscription->organization?->name,
                'organization_id' => $subscription->organization_id,
                'plan' => $subscription->plan?->name,
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'price' => $subscription->price,
                'starts_at' => $subscription->starts_at->toDateString(),
                'ends_at' => $subscription->ends_at->toDateString(),
                'paid_count' => (int) $subscription->paid_count,
            ]);

        return Inertia::render('admin/subscriptions/index', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'per_page' => (int) ($validated['per_page'] ?? 10),
            ],
            'payments' => Inertia::defer(fn () => $this->recentPayments()),
        ]);
    }

    /**
     * Show the form for creating a subscription.
     */
    public function create(Request $request): Response
    {
        $limits = $this->limitsFrom($request);
        $features = $this->featuresFrom($request);

        return Inertia::render('admin/subscriptions/create', [
            'organizations' => Organization::orderBy('name')->get(['id', 'name'])->all(),
            'plans' => Plan::query()
                ->offered()
                ->where('is_active', true)
                ->orderBy('price_monthly')
                ->get(['id', 'name', 'price_monthly', 'price_yearly'])
                ->all(),
            'rates' => $this->pricing->rates(),
            'quote' => $this->pricing->quote($limits, $features),
            'limits' => $limits,
            'features' => $features,
        ]);
    }

    /**
     * Limits arriving on the query string while the form is being filled in.
     *
     * @return array<string, int|null>
     */
    private function limitsFrom(Request $request): array
    {
        $limits = [];

        foreach (PricingCalculator::RESOURCES as $resource) {
            $value = $request->input("limits.{$resource}");

            $limits[$resource] = ($value === null || $value === '')
                ? null
                : max(1, (int) $value);
        }

        return $limits;
    }

    /**
     * Capabilities arriving on the query string while the form is being filled in (docs/project.md F6/F9).
     *
     * @return array<string, bool>
     */
    private function featuresFrom(Request $request): array
    {
        $features = [];

        foreach (PricingCalculator::FEATURES as $feature) {
            $features[$feature] = $request->boolean("features.{$feature}");
        }

        return $features;
    }

    /**
     * Create a subscription from the submitted form.
     */
    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $organization = Organization::query()->findOrFail((int) $validated['organization_id']);

        $plan = $request->isCustom()
            ? $this->buildCustomPlan($request, $organization)
            : Plan::query()->findOrFail((int) $validated['plan_id']);

        $this->subscriptions->assign(
            $organization,
            $plan,
            $validated['billing_cycle'],
            Carbon::parse($validated['starts_at'])->startOfDay(),
            Carbon::parse($validated['ends_at'])->startOfDay(),
        );

        return to_route('admin.subscriptions.index')
            ->with('success', "Abonnement attribue a {$organization->name}.");
    }

    /**
     * Create the one-off plan behind a bespoke subscription.
     */
    private function buildCustomPlan(StoreSubscriptionRequest $request, Organization $organization): Plan
    {
        $limits = $request->limits();
        $features = $request->features();
        $quote = $this->pricing->quote($limits, $features);

        $monthly = $quote['monthly'];
        $yearly = $quote['yearly'];

        $override = $request->input('price_override');

        if ($override !== null && $override !== '') {
            if ($request->input('billing_cycle') === 'yearly') {
                $yearly = round((float) $override, 2);
            } else {
                $monthly = round((float) $override, 2);
            }
        }

        return Plan::create([
            'name' => $request->string('name')->toString(),
            'slug' => $this->uniqueSlug($organization),
            'description' => "Abonnement sur mesure pour {$organization->name}.",
            'price_monthly' => $monthly,
            'price_yearly' => $yearly,
            'max_alumni' => $limits['alumni'],
            'max_events' => $limits['events'],
            'max_campaigns' => $limits['campaigns'],
            'max_users' => $limits['users'],
            'alumni_portal' => $features['alumni_portal'] ?? false,
            'is_active' => false,
            'is_custom' => true,
        ]);
    }

    /**
     * A slug for a bespoke plan that no other plan uses.
     */
    private function uniqueSlug(Organization $organization): string
    {
        $base = 'sur-mesure-'.$organization->slug;
        $slug = $base;
        $suffix = 2;

        while (Plan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Subscription $subscription): RedirectResponse
    {
        if ($subscription->status === 'canceled') {
            return back()->with('error', 'Cet abonnement est deja annule.');
        }

        $this->subscriptions->cancel($subscription);

        return back()->with('success', 'Abonnement annule.');
    }

    /**
     * Payment history (docs/project.md F8).
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentPayments(): array
    {
        return Payment::withoutGlobalScopes()
            ->with(['organization:id,name'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'organization' => $payment->organization?->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'provider' => $payment->provider,
                'provider_reference' => $payment->provider_reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])
            ->all();
    }
}
