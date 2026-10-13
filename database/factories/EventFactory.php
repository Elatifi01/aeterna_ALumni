<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-6 months', '+6 months');

        return [
            'organization_id' => Organization::factory(),
            'created_by' => null,
            'title' => fake()->randomElement([
                'Soiree des anciens', 'Forum des metiers', 'Ceremonie de remise des diplomes',
                'Petit-dejeuner reseau', 'Conference carriere', 'Gala annuel',
            ]).' '.fake()->year(),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+3 hours'),
            'capacity' => fake()->randomElement([null, 30, 50, 100, 200]),
            'status' => fake()->randomElement(['draft', 'published', 'closed']),
        ];
    }

    /**
     * A published event.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'published']);
    }
}
