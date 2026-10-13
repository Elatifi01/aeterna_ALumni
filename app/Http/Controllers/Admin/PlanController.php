<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plans (docs/project.md F6): pricing, per-resource limits and the Paddle price identifiers.
 */
class PlanController extends Controller
{
    /**
     * List the plans.
     */
    public function index(): Response
    {
        $plans = Plan::query()
            ->offered()
            ->withCount(['subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => $this->present($plan));

        return Inertia::render('admin/plans/index', [
            'plans' => $plans,
            'customPlansCount' => Plan::query()->where('is_custom', true)->count(),
        ]);
    }

    /**
     * Show the form for creating a plan.
     */
    public function create(): Response
    {
        return Inertia::render('admin/plans/create');
    }

    /**
     * Create a plan from the submitted form.
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $plan = Plan::create($validated + ['slug' => $this->uniqueSlug($validated['name'])]);

        return to_route('admin.plans.index')->with('success', "Plan {$plan->name} cree.");
    }

    /**
     * Show the form for editing a plan.
     */
    public function edit(Plan $plan): Response
    {
        abort_if($plan->is_custom, 404);

        $plan->loadCount([
            'subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', 'active'),
        ]);

        return Inertia::render('admin/plans/edit', [
            'plan' => $this->present($plan) + ['slug' => $plan->slug],
        ]);
    }

    /**
     * Update a plan from the submitted form.
     */
    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return to_route('admin.plans.index')->with('success', 'Plan mis a jour.');
    }

    /**
     * Delete a plan.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        abort_if($plan->is_custom, 404);

        if ($plan->subscriptions()->exists()) {
            return back()->with(
                'error',
                'Ce plan a deja ete attribue. Desactivez-le plutot que de le supprimer.',
            );
        }

        $plan->delete();

        return to_route('admin.plans.index')->with('success', 'Plan supprime.');
    }

    /**
     * The plan as the admin screens display it.
     *
     * @return array<string, mixed>
     */
    private function present(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'max_alumni' => $plan->max_alumni,
            'max_events' => $plan->max_events,
            'max_campaigns' => $plan->max_campaigns,
            'max_users' => $plan->max_users,
            'paddle_price_monthly_id' => $plan->paddle_price_monthly_id,
            'paddle_price_yearly_id' => $plan->paddle_price_yearly_id,
            'is_active' => $plan->is_active,
            'is_custom' => $plan->is_custom,
            'active_subscriptions_count' => $plan->active_subscriptions_count ?? 0,
        ];
    }

    /**
     * A slug derived from the name that no other plan uses.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $suffix = 2;

        while (Plan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
