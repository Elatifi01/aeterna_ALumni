<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migration: the Super Administrator role now holds the platform catalogue, every entry granted, instead of the staff one it never used.
     */
    public function up(): void
    {
        $ids = [];

        foreach (Permissions::PLATFORM_CATALOGUE as $module => $permissions) {
            foreach ($permissions as $slug => $label) {
                $ids[] = Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['slug' => $slug, 'name' => $label, 'module' => $module],
                )->id;
            }
        }

        $role = Role::withoutGlobalScopes()
            ->whereNull('organization_id')
            ->where('slug', Role::PLATFORM_SLUG)
            ->first();

        if ($role === null) {
            return;
        }

        $role->update(['guard' => 'platform']);
        $role->permissions()->sync($ids);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $role = Role::withoutGlobalScopes()
            ->whereNull('organization_id')
            ->where('slug', Role::PLATFORM_SLUG)
            ->first();

        if ($role !== null) {
            $role->update(['guard' => 'web']);
            $role->permissions()->sync(Permission::whereIn('slug', Permissions::slugs())->pluck('id'));
        }

        Permission::whereIn('slug', Permissions::platformSlugs())->delete();
    }
};
