<?php

namespace Database\Seeders;

use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RoleProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * docs/database-schema.md §9.5 — development data.
 */
class DemoOrganizationSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $free = Plan::where('slug', 'free')->firstOrFail();

        $this->seedOrganization(
            name: 'Universite Hassan II',
            slug: 'universite-hassan-ii',
            email: 'contact@uh2.test',
            adminEmail: 'admin@uh2.test',
            staffEmail: 'staff@uh2.test',
            plan: $pro,
            alumniCount: 40,
            eventCount: 5,
            campaignCount: 3,
        );

        $this->seedOrganization(
            name: 'Ecole Centrale Casablanca',
            slug: 'ecole-centrale-casablanca',
            email: 'contact@ecc.test',
            adminEmail: 'admin@ecc.test',
            staffEmail: 'staff@ecc.test',
            plan: $free,
            alumniCount: 15,
            eventCount: 2,
            campaignCount: 1,
        );
    }

    /**
     * Create one demo organization with its roles, staff and sample data.
     */
    private function seedOrganization(
        string $name,
        string $slug,
        string $email,
        string $adminEmail,
        string $staffEmail,
        Plan $plan,
        int $alumniCount,
        int $eventCount,
        int $campaignCount,
    ): void {
        $organization = Organization::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'email' => $email, 'phone' => '+212 5 22 00 00 00', 'is_active' => true],
        );

        Subscription::withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $organization->id, 'plan_id' => $plan->id],
            [
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'price' => $plan->price_monthly,
                'starts_at' => now()->startOfDay(),
                'ends_at' => now()->addMonth()->startOfDay(),
            ],
        );

        $roles = app(RoleProvisioner::class)->provision($organization);
        $adminRole = $roles['org-admin'];
        $staffRole = $roles['staff'];

        $admin = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'organization_id' => $organization->id,
                'role_id' => $adminRole->id,
                'name' => 'Administrateur '.$name,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $staffEmail],
            [
                'organization_id' => $organization->id,
                'role_id' => $staffRole->id,
                'name' => 'Personnel '.$name,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if (Alumni::withoutGlobalScopes()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        $alumni = Alumni::factory()
            ->count($alumniCount)
            ->create(['organization_id' => $organization->id]);

        $events = Event::factory()
            ->count($eventCount)
            ->create(['organization_id' => $organization->id, 'created_by' => $admin->id]);

        foreach ($events as $event) {
            $registrants = $alumni->random(min($alumni->count(), random_int(3, 12)));

            foreach ($registrants as $person) {
                $attended = fake()->boolean(65);

                $event->alumni()->attach($person->id, [
                    'status' => 'registered',
                    'registered_at' => now()->subDays(random_int(1, 40)),
                    'attended' => $attended,
                    'attended_at' => $attended ? now()->subDays(random_int(0, 5)) : null,
                ]);
            }
        }

        Campaign::factory()
            ->count($campaignCount)
            ->create(['organization_id' => $organization->id, 'created_by' => $admin->id]);
    }
}
