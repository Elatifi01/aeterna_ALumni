<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $provisioner = new RoleProvisioner;

        DB::transaction(function () use ($provisioner): void {
            foreach (Organization::withoutGlobalScopes()->withTrashed()->get() as $organization) {
                $roles = $provisioner->provision($organization);

                foreach ($roles as $slug => $role) {
                    $template = Role::withoutGlobalScopes()
                        ->whereNull('organization_id')
                        ->where('slug', $slug)
                        ->first();

                    if ($template === null) {
                        continue;
                    }

                    User::withoutGlobalScopes()
                        ->withTrashed()
                        ->where('organization_id', $organization->id)
                        ->where('role_id', $template->id)
                        ->update(['role_id' => $role->id]);
                }
            }
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (RoleProvisioner::DEFAULT_SLUGS as $slug) {
                $template = Role::withoutGlobalScopes()
                    ->whereNull('organization_id')
                    ->where('slug', $slug)
                    ->first();

                if ($template === null) {
                    continue;
                }

                $owned = Role::withoutGlobalScopes()
                    ->whereNotNull('organization_id')
                    ->where('slug', $slug)
                    ->get();

                foreach ($owned as $role) {
                    User::withoutGlobalScopes()
                        ->withTrashed()
                        ->where('role_id', $role->id)
                        ->update(['role_id' => $template->id]);

                    $role->permissions()->detach();
                    $role->forceDelete();
                }
            }
        });
    }
};
