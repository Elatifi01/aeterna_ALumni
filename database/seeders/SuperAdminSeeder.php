<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * docs/database-schema.md §9.4 — one platform account, attached to no organization.
 */
class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        $role = Role::withoutGlobalScopes()
            ->where('slug', Role::PLATFORM_SLUG)
            ->whereNull('organization_id')
            ->firstOrFail();

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'admin@aeterna.test'],
            [
                'organization_id' => null,
                'role_id' => $role->id,
                'name' => 'Super administrateur',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
