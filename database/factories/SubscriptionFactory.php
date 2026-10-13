<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_id' => Plan::factory(),
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'price' => fake()->randomFloat(2, 0, 199),
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addMonth()->startOfDay(),
            'canceled_at' => null,
        ];
    }

    /**
     * A subscription whose end date has passed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
