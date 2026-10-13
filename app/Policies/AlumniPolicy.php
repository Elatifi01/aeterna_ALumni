<?php

namespace App\Policies;

use App\Models\Alumni;
use App\Models\User;

/**
 * Authorization for alumni (docs/project.md §5.4).
 */
class AlumniPolicy
{
    /**
     * Whether the user may list alumni.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('alumni.view');
    }

    /**
     * Whether the user may view this alumni.
     */
    public function view(User $user, Alumni $alumni): bool
    {
        return $user->hasPermission('alumni.view') && $this->sameOrganization($user, $alumni);
    }

    /**
     * Whether the user may add alumni.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('alumni.create');
    }

    /**
     * Whether the user may edit this alumni.
     */
    public function update(User $user, Alumni $alumni): bool
    {
        return $user->hasPermission('alumni.update') && $this->sameOrganization($user, $alumni);
    }

    /**
     * Whether the user may delete alumni at all.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('alumni.delete');
    }

    /**
     * Whether the user may delete this alumni.
     */
    public function delete(User $user, Alumni $alumni): bool
    {
        return $user->hasPermission('alumni.delete') && $this->sameOrganization($user, $alumni);
    }

    /**
     * Sending an alumni their portal credentials (docs/project.md F9).
     */
    public function invite(User $user, Alumni $alumni): bool
    {
        return $user->hasPermission('alumni.invite') && $this->sameOrganization($user, $alumni);
    }

    /**
     * Sending credentials to a whole group at once (docs/project.md F12).
     */
    public function inviteAny(User $user): bool
    {
        return $user->hasPermission('alumni.invite');
    }

    /**
     * Turning one alumni's portal access on or off (docs/project.md F9).
     */
    public function managePortal(User $user, Alumni $alumni): bool
    {
        return $user->hasPermission('alumni.portal') && $this->sameOrganization($user, $alumni);
    }

    /**
     * Importing a spreadsheet (docs/project.md F12).
     */
    public function import(User $user): bool
    {
        return $user->hasPermission('alumni.import');
    }

    /**
     * Exporting the list to a spreadsheet (docs/project.md F13).
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('alumni.export');
    }

    /**
     * Restoring and permanently deleting are the same capability as deleting: the permission catalogue (docs/project.md §5.4) defines no separate slug, and whoever may remove a record may put it back.
     */
    public function restore(User $user, Alumni $alumni): bool
    {
        return $this->delete($user, $alumni);
    }

    /**
     * Whether the user may permanently delete this alumni.
     */
    public function forceDelete(User $user, Alumni $alumni): bool
    {
        return $this->delete($user, $alumni);
    }

    /**
     * Whether the alumni belongs to the user's organization.
     */
    private function sameOrganization(User $user, Alumni $alumni): bool
    {
        return $user->organization_id !== null
            && $user->organization_id === $alumni->organization_id;
    }
}
