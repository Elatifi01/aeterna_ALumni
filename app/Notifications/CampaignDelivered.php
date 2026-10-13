<?php

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A campaign has reached this alumni (docs/project.md F10).
 */
class CampaignDelivered extends Notification
{
    use Queueable;

    /**
     * Create a new instance.
     */
    public function __construct(public readonly Campaign $campaign) {}

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
            'type' => 'campaign',
            'campaign_id' => $this->campaign->id,
            'title' => $this->campaign->subject,
            'body' => 'Vous avez recu un nouveau message.',
        ];
    }
}
