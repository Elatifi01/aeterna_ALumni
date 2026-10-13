<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subscription = Subscription::factory();

        return [
            'subscription_id' => $subscription,
            'organization_id' => fn (array $attributes) => Subscription::query()
                ->findOrFail((int) $attributes['subscription_id'])
                ->organization_id,
            'amount' => fake()->randomFloat(2, 10, 199),
            'currency' => 'USD',
            'status' => 'paid',
            'provider' => 'paddle',
            'provider_reference' => 'txn_'.Str::lower(Str::random(24)),
            'paid_at' => now(),
        ];
    }
}
