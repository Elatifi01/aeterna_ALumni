<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var list<array<string, string>> */
    private const NEW_PERMISSIONS = [
        [
            'slug' => 'alumni.portal',
            'name' => 'Gerer l acces des alumni au portail',
            'module' => 'alumni',
        ],
        [
            'slug' => 'alumni.import',
            'name' => 'Importer des alumni depuis un fichier',
            'module' => 'alumni',
        ],
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $ids = Permission::whereIn('slug', $this->slugs())->pluck('id');

        Role::withoutGlobalScopes()
            ->where('slug', 'org-admin')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $ids = Permission::whereIn('slug', $this->slugs())->pluck('id');

        Role::withoutGlobalScopes()
            ->where('slug', 'org-admin')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->detach($ids));
    }

    /**
     * The permission slugs this migration grants.
     *
     * @return list<string>
     */
    private function slugs(): array
    {
        return array_column(self::NEW_PERMISSIONS, 'slug');
    }
};
