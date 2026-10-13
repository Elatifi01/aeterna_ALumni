<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => fake()->randomFloat(2, 0, 199),
            'price_yearly' => fake()->randomFloat(2, 0, 1990),
            'max_alumni' => 500,
            'max_events' => 20,
            'max_campaigns' => 20,
            'max_users' => 5,
            'alumni_portal' => false,
            'is_active' => true,
        ];
    }

    /**
     * With the alumni portal (docs/project.md F9).
     */
    public function withPortal(): static
    {
        return $this->state(fn (array $attributes) => [
            'alumni_portal' => true,
        ]);
    }

    /**
     * Every limit removed.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_alumni' => null,
            'max_events' => null,
            'max_campaigns' => null,
            'max_users' => null,
        ]);
    }
}
