<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
class CampaignFactory extends Factory
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
            'created_by' => null,
            'subject' => fake()->randomElement([
                'Invitation a la soiree des anciens',
                'Votre newsletter trimestrielle',
                'Appel a temoignages pour le forum des metiers',
                'Save the date : gala annuel',
            ]),
            'content' => fake()->paragraphs(3, true),
            'status' => 'draft',
            'recipients_count' => 0,
            'sent_at' => null,
        ];
    }
}
