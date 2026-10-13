<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Authorization for events (docs/project.md §5.4).
 */
class EventPolicy
{
    /**
     * Whether the user may list events.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('events.view');
    }

    /**
     * Whether the user may view this event.
     */
    public function view(User $user, Event $event): bool
    {
        return $user->hasPermission('events.view') && $this->sameOrganization($user, $event);
    }

    /**
     * Whether the user may create events.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('events.create');
    }

    /**
     * Whether the user may edit this event.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->hasPermission('events.update') && $this->sameOrganization($user, $event);
    }

    /**
     * Whether the user may delete events at all.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('events.delete');
    }

    /**
     * Whether the user may delete this event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->hasPermission('events.delete') && $this->sameOrganization($user, $event);
    }

    /**
     * Restoring is the same capability as deleting (docs/project.md §5.4).
     */
    public function restore(User $user, Event $event): bool
    {
        return $this->delete($user, $event);
    }

    /**
     * Whether the user may permanently delete this event.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $this->delete($user, $event);
    }

    /**
     * Whether the user may register alumni and mark attendance.
     */
    public function manageRegistrations(User $user, Event $event): bool
    {
        return $user->hasPermission('events.registrations') && $this->sameOrganization($user, $event);
    }

    /**
     * Whether the event belongs to the user's organization.
     */
    private function sameOrganization(User $user, Event $event): bool
    {
        return $user->organization_id !== null
            && $user->organization_id === $event->organization_id;
    }
}
