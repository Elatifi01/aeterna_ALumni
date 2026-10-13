<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for internal user accounts (docs/project.md F2).
 */
class UserPolicy
{
    /**
     * Whether the user may list users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    /**
     * Whether the user may view this user.
     */
    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view') && $this->sameOrganization($user, $target);
    }

    /**
     * Whether the user may create users.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    /**
     * Whether the user may edit this user.
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.update') && $this->sameOrganization($user, $target);
    }

    /**
     * Whether the user may activate or deactivate this user.
     */
    public function toggleActive(User $user, User $target): bool
    {
        return $user->hasPermission('users.update')
            && $this->sameOrganization($user, $target)
            && $user->id !== $target->id;
    }

    /**
     * Whether the user may delete this user.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission('users.delete')
            && $this->sameOrganization($user, $target)
            && $user->id !== $target->id;
    }

    /**
     * Restoring and permanently deleting map to users.delete.
     */
    public function restore(User $user, User $target): bool
    {
        return $user->hasPermission('users.delete') && $this->sameOrganization($user, $target);
    }

    /**
     * Whether the user may permanently delete this user.
     */
    public function forceDelete(User $user, User $target): bool
    {
        return $this->restore($user, $target);
    }

    /**
     * Whether both users belong to the same organization.
     */
    private function sameOrganization(User $user, User $target): bool
    {
        return $user->organization_id !== null
            && $target->organization_id !== null
            && $user->organization_id === $target->organization_id;
    }
}
