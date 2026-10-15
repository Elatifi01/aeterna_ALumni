<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * docs/database-schema.md §9.2 — the system roles: the platform's, two for staff, one for the portal.
 */
class RoleSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        $permissionIds = Permission::pluck('id', 'slug');

        $roles = [
            [
                'slug' => Role::PLATFORM_SLUG,
                'name' => 'Super administrateur',
                'description' => 'Acces global a la plateforme.',
                'guard' => 'platform',
                'permissions' => Permissions::platformSlugs(),
            ],
            [
                'slug' => 'org-admin',
                'name' => 'Administrateur d organisation',
                'description' => 'Gere les alumni, evenements, campagnes et utilisateurs de son organisation.',
                'permissions' => Permissions::slugs(),
            ],
            [
                'slug' => 'staff',
                'name' => 'Personnel',
                'description' => 'Acces en consultation, selon les permissions accordees.',
                'permissions' => Permissions::viewOnlySlugs(),
            ],
            [
                'slug' => Permissions::ALUMNI_ROLE_SLUG,
                'name' => 'Alumni',
                'description' => 'Ce que les anciens etudiants peuvent faire dans leur portail.',
                'guard' => 'alumni',
                'permissions' => Permissions::portalSlugs(),
            ],
        ];

        foreach ($roles as $definition) {
            $role = Role::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => null, 'slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                    'guard' => $definition['guard'] ?? 'web',
                ],
            );

            $role->permissions()->sync(
                collect($definition['permissions'])->map(fn (string $slug) => $permissionIds[$slug])->all(),
            );
        }
    }
}
