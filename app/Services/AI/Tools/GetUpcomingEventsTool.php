<?php

namespace App\Services\AI\Tools;

use App\Models\Event;
use App\Services\AI\Actor;

/**
 * The establishment's coming events (docs/project.md F14).
 */
class GetUpcomingEventsTool implements Tool
{
    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'get_upcoming_events';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Liste les prochains evenements publies par l etablissement de l utilisateur. '
            .'A utiliser pour toute question sur les evenements, rencontres ou dates a venir.';
    }

    /**
     * The JSON schema of the tool's arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Nombre maximum d evenements a renvoyer (1 a 20).',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Whether this actor may use the tool.
     */
    public function authorize(Actor $actor): bool
    {
        return $actor->isAlumni()
            ? $actor->can('portal.events.view')
            : $actor->can('events.view');
    }

    /**
     * Run the tool with the arguments the model supplied.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments, Actor $actor): array
    {
        $limit = is_numeric($arguments['limit'] ?? null)
            ? min(20, max(1, (int) $arguments['limit']))
            : 10;

        $events = Event::query()
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        return [
            'count' => $events->count(),
            'events' => $events->map(fn (Event $event): array => [
                'title' => $event->title,
                'starts_at' => $event->starts_at->toDateTimeString(),
                'location' => $event->location,
                'capacity' => $event->capacity,
                'seats_taken' => $event->registrations()->count(),
                'description' => $event->description,
            ])->all(),
        ];
    }
}
