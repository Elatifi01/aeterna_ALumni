<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * docs/database-schema.md §9 — the order matters: permissions before roles (roles sync permission ids), roles before users (users.role_id is RESTRICT), and plans before the demo organizations (each gets an active subscription).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            PlanSeeder::class,
            SuperAdminSeeder::class,
            DemoOrganizationSeeder::class,
        ]);
    }
}
