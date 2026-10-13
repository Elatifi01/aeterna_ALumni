<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Gives an organization its own copy of the default roles.
 */
class RoleProvisioner
{
    public const DEFAULT_SLUGS = ['org-admin', 'staff', 'alumni'];

    /**
     * Create the organization's own roles, copying each template's name, description and permissions.
     *
     * @return array<string, Role> keyed by slug
     */
    public function provision(Organization $organization): array
    {
        return DB::transaction(function () use ($organization) {
            $roles = [];

            foreach (self::DEFAULT_SLUGS as $slug) {
                $roles[$slug] = $this->copyTemplate($organization, $slug);
            }

            return $roles;
        });
    }

    /**
     * The organization's administrator role, provisioning it if missing.
     */
    public function administratorRoleFor(Organization $organization): Role
    {
        return $this->copyTemplate($organization, 'org-admin');
    }

    /**
     * The organization's copy of a template role, created if missing.
     */
    private function copyTemplate(Organization $organization, string $slug): Role
    {
        $existing = Role::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $template = Role::withoutGlobalScopes()
            ->whereNull('organization_id')
            ->where('slug', $slug)
            ->first();

        $role = new Role([
            'name' => $template->name ?? ucfirst($slug),
            'slug' => $slug,
            'description' => $template?->description,
            'is_system' => false,
            'guard' => $template->guard ?? ($slug === Permissions::ALUMNI_ROLE_SLUG ? 'alumni' : 'web'),
        ]);
        $role->organization_id = $organization->id;
        $role->save();

        if ($template !== null) {
            $role->permissions()->sync($template->permissions()->pluck('permissions.id'));
        } elseif ($role->isAlumniRole()) {
            $role->permissions()->sync(
                Permission::whereIn('slug', Permissions::portalSlugs())->pluck('id'),
            );
        }

        return $role;
    }
}
