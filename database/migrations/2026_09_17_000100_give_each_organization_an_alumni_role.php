<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> slug => label */
    private const PORTAL_PERMISSIONS = [
        'portal.events.view' => 'Consulter les evenements',
        'portal.events.register' => 'S inscrire aux evenements',
        'portal.messages.view' => 'Lire les messages recus',
        'portal.notifications.view' => 'Consulter les notifications',
        'portal.jobs.view' => 'Consulter les offres d emploi',
        'portal.jobs.apply' => 'Enregistrer et postuler aux offres',
        'portal.assistant.use' => 'Utiliser Aeterna Assistant',
        'portal.profile.update' => 'Modifier son profil',
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('guard', 20)->default('web')->after('is_system');
        });

        $permissionIds = [];

        foreach (self::PORTAL_PERMISSIONS as $slug => $label) {
            $permissionIds[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['slug' => $slug, 'name' => $label, 'module' => 'portal'],
            )->id;
        }

        $template = Role::withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => null, 'slug' => 'alumni'],
            [
                'name' => 'Alumni',
                'description' => 'Ce que les anciens etudiants peuvent faire dans leur portail.',
                'is_system' => true,
                'guard' => 'alumni',
            ],
        );
        $template->permissions()->sync(array_values($permissionIds));

        $switches = Schema::hasColumn('organizations', 'portal_assistant')
            ? DB::table('organizations')->pluck('portal_assistant', 'id')
            : collect();

        foreach (Organization::withoutGlobalScopes()->get() as $organization) {
            $role = Role::withoutGlobalScopes()->firstOrNew([
                'organization_id' => $organization->id,
                'slug' => 'alumni',
            ]);

            $role->fill([
                'name' => 'Alumni',
                'description' => 'Ce que les anciens etudiants peuvent faire dans leur portail.',
                'is_system' => false,
                'guard' => 'alumni',
            ]);
            $role->organization_id = $organization->id;
            $role->deleted_at = null;
            $role->save();

            $granted = $permissionIds;

            if (($switches[$organization->id] ?? true) == false) {
                unset($granted['portal.assistant.use']);
            }

            $role->permissions()->sync(array_values($granted));
        }

        if (Schema::hasColumn('organizations', 'portal_assistant')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('portal_assistant');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('portal_assistant')->default(true)->after('logo_path');
        });

        foreach (Role::withoutGlobalScopes()->where('slug', 'alumni')->whereNotNull('organization_id')->get() as $role) {
            DB::table('organizations')->where('id', $role->organization_id)->update([
                'portal_assistant' => $role->permissions()->where('slug', 'portal.assistant.use')->exists(),
            ]);
        }

        Role::withoutGlobalScopes()->where('slug', 'alumni')->each(function (Role $role): void {
            $role->permissions()->detach();
            $role->forceDelete();
        });

        Permission::whereIn('slug', array_keys(self::PORTAL_PERMISSIONS))->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('guard');
        });
    }
};
