<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * docs/database-schema.md §9.3 — the Free and Pro plans named in docs/project.md §3.6.
 */
class PlanSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Pour demarrer : un volume limite d alumni, d evenements et de campagnes.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'max_alumni' => 100,
                'max_events' => 3,
                'max_campaigns' => 3,
                'max_users' => 2,
                'alumni_portal' => false,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Pour un suivi complet : volumes etendus et equipe elargie.',
                'price_monthly' => 49,
                'price_yearly' => 490,
                'max_alumni' => 5000,
                'max_events' => 100,
                'max_campaigns' => 100,
                'max_users' => 20,
                'alumni_portal' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan + ['is_active' => true]);
        }
    }
}
