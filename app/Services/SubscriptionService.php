<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Assigning and ending subscriptions (docs/project.md F1, F6).
 */
class SubscriptionService
{
    /**
     * Give an organization a new active subscription, ending whatever it had.
     */
    public function assign(
        Organization $organization,
        Plan $plan,
        string $billingCycle,
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
    ): Subscription {
        $startsAt ??= now()->startOfDay();
        $endsAt ??= $billingCycle === 'yearly'
            ? (clone $startsAt)->addYear()
            : (clone $startsAt)->addMonth();

        return DB::transaction(function () use ($organization, $plan, $billingCycle, $startsAt, $endsAt) {
            $this->endCurrent($organization);

            return $organization->subscriptions()->create([
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'price' => $plan->priceFor($billingCycle),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        });
    }

    /**
     * Mark the organization's current subscription as expired, if it has one.
     */
    public function endCurrent(Organization $organization): void
    {
        Subscription::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    /**
     * Flip subscriptions whose end date has passed to 'expired'.
     *
     * @return int number of subscriptions expired
     */
    public function expireOverdue(): int
    {
        return Subscription::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }
}
