<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The alumni's place at an event is confirmed (docs/project.md F10).
 */
class RegistrationConfirmed extends Notification
{
    use Queueable;

    /**
     * Create a new instance.
     */
    public function __construct(public readonly Event $event) {}

    /**
     * The channels the notification is delivered on.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The payload stored for the in-app notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'registration',
            'event_id' => $this->event->id,
            'title' => $this->event->title,
            'body' => 'Votre inscription est confirmee.',
            'starts_at' => $this->event->starts_at->toIso8601String(),
        ];
    }
}
