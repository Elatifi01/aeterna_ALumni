<?php

namespace Database\Factories;

use App\Models\Alumni;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Alumni> */
class AlumniFactory extends Factory
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'degree' => fake()->randomElement([
                'Licence Informatique', 'Master Management', 'Master Data Science',
                'Diplome d ingenieur', 'Licence Droit', 'Master Marketing',
            ]),
            'graduation_year' => fake()->numberBetween(2005, 2025),
            'company' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'city' => fake()->city(),
        ];
    }
}
