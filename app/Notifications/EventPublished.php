<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An event has been published by the alumni's establishment (docs/project.md F10).
 */
class EventPublished extends Notification
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
            'type' => 'event',
            'event_id' => $this->event->id,
            'title' => $this->event->title,
            'body' => 'Un nouvel evenement vient d etre publie.',
            'starts_at' => $this->event->starts_at->toIso8601String(),
        ];
    }
}
