<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * docs/database-schema.md §9.1 — the catalogue of docs/project.md §5.4.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        foreach (Permissions::rows() as $row) {
            Permission::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
