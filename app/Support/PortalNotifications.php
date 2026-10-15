<?php

namespace App\Support;

use App\Models\Alumni;
use Illuminate\Notifications\DatabaseNotification;

/**
 * How an alumni's notification reaches the portal interface: the full list and the header bell read the same shape.
 */
final class PortalNotifications
{
    public const RECENT_LIMIT = 5;

    /**
     * The latest notifications for the header bell, or none when the alumni may not see notifications.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentFor(Alumni $alumni): array
    {
        if (! $alumni->hasPermission('portal.notifications.view')) {
            return [];
        }

        return array_values($alumni->notifications()
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => self::present($notification))
            ->all());
    }

    /**
     * The stored notification, narrowed to what the interface renders.
     *
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'kind' => is_string($data['type'] ?? null) ? $data['type'] : 'other',
            'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
            'body' => is_string($data['body'] ?? null) ? $data['body'] : '',
            'event_id' => is_int($data['event_id'] ?? null) ? $data['event_id'] : null,
            'campaign_id' => is_int($data['campaign_id'] ?? null) ? $data['campaign_id'] : null,
        ];
    }
}
