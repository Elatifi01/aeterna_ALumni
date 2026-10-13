<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SLUG = 'alumni.export';

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => self::SLUG],
            ['slug' => self::SLUG, 'name' => 'Exporter la liste des alumni', 'module' => 'alumni'],
        );

        Role::withoutGlobalScopes()
            ->where('slug', 'org-admin')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $id = Permission::where('slug', self::SLUG)->value('id');

        if ($id === null) {
            return;
        }

        Role::withoutGlobalScopes()
            ->where('slug', 'org-admin')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->detach($id));
    }
};
