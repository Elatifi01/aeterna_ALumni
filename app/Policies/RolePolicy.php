<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Authorization for roles (docs/project.md §5.4).
 */
class RolePolicy
{
    /**
     * Whether the user may list roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    /**
     * Whether the user may view this role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view') && $this->ownedBy($user, $role);
    }

    /**
     * Whether the user may create roles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    /**
     * Whether the user may edit this role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && $this->ownedBy($user, $role);
    }

    /**
     * The alumni role is the one exception: it is the organization's to edit but not to remove, because every one of its alumni holds it and there is nothing else for them to hold.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage')
            && $this->ownedBy($user, $role)
            && ! $role->isAlumniRole();
    }

    /**
     * Restoring and permanently deleting are the same capability as deleting: the catalogue (docs/project.md §5.4) defines only roles.view and roles.manage, and whoever may bin a role may bring it back.
     */
    public function restore(User $user, Role $role): bool
    {
        return $this->delete($user, $role);
    }

    /**
     * Whether the user may permanently delete this role.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $this->delete($user, $role);
    }

    /**
     * An organization sees and manages only its own roles; the platform roles belong to the Super Administrator.
     */
    private function ownedBy(User $user, Role $role): bool
    {
        return ! $role->is_system
            && $role->organization_id !== null
            && $role->organization_id === $user->organization_id;
    }
}
