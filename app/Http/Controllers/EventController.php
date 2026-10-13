<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitReachedException;
use App\Http\Controllers\Concerns\DeletesSelection;
use App\Http\Requests\Event\IndexEventRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Alumni;
use App\Models\Event;
use App\Services\AlumniNotifier;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Event management (docs/project.md F4).
 */
class EventController extends Controller
{
    use DeletesSelection;

    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly PlanLimitService $planLimits,
        private readonly AlumniNotifier $notifier,
    ) {}

    /**
     * List the events, filtered and paginated.
     */
    public function index(IndexEventRequest $request): Response
    {
        $filters = $request->safe()->only(['search', 'status', 'sort', 'direction', 'per_page']);

        $sort = $filters['sort'] ?? 'starts_at';
        $direction = $filters['direction'] ?? 'desc';

        $events = Event::query()
            ->withCount([
                'alumni as registrations_count' => fn ($query) => $query->where('alumni_event.status', 'registered'),
                'alumni as attendees_count' => fn ($query) => $query
                    ->where('alumni_event.status', 'registered')
                    ->where('alumni_event.attended', true),
            ])
            ->when($filters['search'] ?? null, function ($query, string $term) {
                $like = '%'.addcslashes($term, '%_').'%';

                $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('location', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (Event $event) => $this->rowFor($event));

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => (int) ($filters['per_page'] ?? 10),
            ],
            'usage' => $this->planLimits->usageFor($request->user()->organization, 'events'),
        ]);
    }

    /**
     * Show the form for creating an event.
     */
    public function create(): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('events/create');
    }

    /**
     * Create an event from the submitted form.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        try {
            $this->planLimits->ensureCanCreate($request->user()->organization, 'events');
        } catch (PlanLimitReachedException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $event = Event::create($request->validated() + ['created_by' => $request->user()->id]);

        if ($event->status === 'published') {
            $this->notifier->eventPublished($event);
        }

        return to_route('events.show', $event)->with('success', 'Evenement cree.');
    }

    /**
     * Show one event with its registrations.
     */
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $registrations = $event->alumni()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($alumni) => [
                'id' => $alumni->id,
                'first_name' => $alumni->first_name,
                'last_name' => $alumni->last_name,
                'email' => $alumni->email,
                'status' => $alumni->pivot->getAttribute('status'),
                'attended' => (bool) $alumni->pivot->getAttribute('attended'),
                'registered_at' => $alumni->pivot->getAttribute('registered_at'),
            ]);

        $active = $registrations->where('status', 'registered');

        return Inertia::render('events/show', [
            'event' => $this->rowFor($event->loadCount([
                'alumni as registrations_count' => fn ($query) => $query->where('alumni_event.status', 'registered'),
                'alumni as attendees_count' => fn ($query) => $query
                    ->where('alumni_event.status', 'registered')
                    ->where('alumni_event.attended', true),
            ])) + ['description' => $event->description],
            'registrations' => $registrations->values(),
            'seatsLeft' => $event->capacity === null
                ? null
                : max(0, $event->capacity - $active->count()),

            'availableAlumni' => Inertia::optional(function () use ($request, $event) {
                $term = $request->string('alumni_search')->toString();

                if ($term === '') {
                    return [];
                }

                return Alumni::query()
                    ->search($term)
                    ->whereDoesntHave('events', fn ($query) => $query
                        ->where('events.id', $event->id)
                        ->where('alumni_event.status', 'registered'))
                    ->orderBy('last_name')
                    ->limit(10)
                    ->get(['id', 'first_name', 'last_name', 'email']);
            }),
            'alumniSearch' => $request->string('alumni_search')->toString() ?: null,
        ]);
    }

    /**
     * Show the form for editing an event.
     */
    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('events/edit', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
                'capacity' => $event->capacity,
                'status' => $event->status,
            ],
        ]);
    }

    /**
     * Update an event from the submitted form.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $wasPublished = $event->status === 'published';

        $event->update($request->validated());

        if (! $wasPublished && $event->status === 'published') {
            $this->notifier->eventPublished($event);
        }

        return to_route('events.show', $event)->with('success', 'Evenement mis a jour.');
    }

    /**
     * Delete an event.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return to_route('events.index')->with('success', 'Evenement supprime.');
    }

    /**
     * Delete the events ticked in the list.
     */
    public function destroySelection(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Event::class);

        $count = $this->deleteSelection($request, Event::query());

        return to_route('events.index')
            ->with('success', $this->selectionDeletedMessage($count, 'evenement supprime', 'evenements supprimes'));
    }

    /**
     * One row of the list, including the attendance rate of docs/database-schema.md §8.
     *
     * @return array<string, mixed>
     */
    private function rowFor(Event $event): array
    {
        $registrations = (int) ($event->registrations_count ?? 0);
        $attendees = (int) ($event->attendees_count ?? 0);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'location' => $event->location,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'capacity' => $event->capacity,
            'status' => $event->status,
            'registrations_count' => $registrations,
            'attendees_count' => $attendees,
            'attendance_rate' => $registrations === 0
                ? null
                : round($attendees / $registrations * 100, 1),
        ];
    }
}
