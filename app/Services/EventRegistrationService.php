<?php

namespace App\Services;

use App\Exceptions\RegistrationRefusedException;
use App\Models\Alumni;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Registering an alumni for an event (docs/project.md F4).
 */
class EventRegistrationService
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly AlumniNotifier $notifier) {}

    /**
     * Register the alumni for the event, applying the seat and lock rules.
     *
     * @throws RegistrationRefusedException when the event is full or the alumni is already registered
     */
    public function register(Event $event, Alumni $alumni): void
    {
        if ($event->organization_id !== $alumni->organization_id) {
            throw new RegistrationRefusedException('Cet evenement n appartient pas a votre organisation.');
        }

        DB::transaction(function () use ($event, $alumni): void {
            $existing = $event->alumni()
                ->lockForUpdate()
                ->wherePivot('alumni_id', $alumni->id)
                ->first();

            if ($existing !== null && $existing->pivot->getAttribute('status') === 'registered') {
                throw new RegistrationRefusedException('Cet alumni est deja inscrit a cet evenement.');
            }

            $this->assertSeatAvailable($event);

            if ($existing !== null) {
                $event->alumni()->updateExistingPivot($alumni->id, [
                    'status' => 'registered',
                    'registered_at' => now(),
                    'attended' => false,
                    'attended_at' => null,
                ]);

                return;
            }

            $event->alumni()->attach($alumni->id, [
                'status' => 'registered',
                'registered_at' => now(),
                'attended' => false,
            ]);
        });

        $this->notifier->registrationConfirmed($event, $alumni);
    }

    /**
     * Cancel a registration.
     */
    public function cancel(Event $event, Alumni $alumni): bool
    {
        if (! $event->alumni()->wherePivot('alumni_id', $alumni->id)->exists()) {
            return false;
        }

        $event->alumni()->updateExistingPivot($alumni->id, [
            'status' => 'canceled',
            'attended' => false,
            'attended_at' => null,
        ]);

        return true;
    }

    /**
     * A NULL capacity means unlimited (docs/database-schema.md §5.10).
     *
     * @throws RegistrationRefusedException
     */
    private function assertSeatAvailable(Event $event): void
    {
        if ($event->capacity === null) {
            return;
        }

        if ($event->registrations()->count() >= $event->capacity) {
            throw new RegistrationRefusedException(
                "Cet evenement affiche complet ({$event->capacity} places).",
            );
        }
    }
}
