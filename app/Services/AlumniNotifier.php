<?php

namespace App\Services;

use App\Models\Alumni;
use App\Models\Event;
use App\Notifications\EventPublished;
use App\Notifications\RegistrationConfirmed;
use Illuminate\Support\Facades\Notification;

/**
 * Telling alumni what has happened (docs/project.md F10).
 */
class AlumniNotifier
{
    /**
     * An event has just become visible to the alumni of its organization.
     */
    public function eventPublished(Event $event): void
    {
        if ($event->starts_at->isPast()) {
            return;
        }

        $recipients = Alumni::acrossOrganizations()
            ->where('organization_id', $event->organization_id)
            ->whereNotNull('password')
            ->where('portal_access', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EventPublished($event));
    }

    /**
     * One alumni's place at an event is confirmed.
     */
    public function registrationConfirmed(Event $event, Alumni $alumni): void
    {
        if (! $alumni->has_account || ! $alumni->portal_access) {
            return;
        }

        $alumni->notify(new RegistrationConfirmed($event));
    }
}
