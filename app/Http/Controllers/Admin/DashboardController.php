<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\EmploiMa\CreditBudget;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform dashboard for the Super Administrator (docs/project.md F7):
 * number of organizations, active subscriptions, distribution per plan, scraper credits.
 */
class DashboardController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly CreditBudget $credits) {}

    /**
     * Show the platform dashboard.
     */
    public function __invoke(): Response
    {
        $organizationsCount = Organization::count();
        $activeOrganizationsCount = Organization::where('is_active', true)->count();
        $activeSubscriptionsCount = Subscription::where('status', 'active')->count();

        $recentOrganizations = Organization::latest()
            ->take(5)
            ->with('activeSubscription.plan')
            ->get();

        $planDistribution = Plan::query()
            ->withCount(['subscriptions' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                'name' => $plan->name,
                'price' => (float) $plan->price_monthly,
                'organizations' => $plan->subscriptions_count,
            ])
            ->all();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'organizations' => $organizationsCount,
                'activeOrganizations' => $activeOrganizationsCount,
                'activeSubscriptions' => $activeSubscriptionsCount,
            ],
            'recentOrganizations' => $recentOrganizations,
            'plans' => Plan::where('is_active', true)->get(),
            'planDistribution' => $planDistribution,
            'jobBoard' => $this->credits->summary(),
        ]);
    }
}
