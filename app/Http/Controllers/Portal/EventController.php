<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\RegistrationRefusedException;
use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Event;
use App\Services\EventRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Events as an alumni sees them (docs/project.md F9).
 */
class EventController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly EventRegistrationService $registrations) {}

    /**
     * List the published events of the alumni's organization.
     */
    public function index(Request $request): Response
    {
        $alumni = $this->alumni($request);

        $events = Event::query()
            ->where('status', 'published')
            ->withCount(['registrations as registrations_count'])
            ->orderBy('starts_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Event $event): array => $this->summarise($event, $alumni));

        return Inertia::render('portal/events/index', [
            'events' => $events,
        ]);
    }

    /**
     * Show one event with the alumni's registration status.
     */
    public function show(Request $request, Event $event): Response
    {
        $alumni = $this->alumni($request);

        abort_unless($event->status === 'published', 404);

        $event->loadCount(['registrations as registrations_count']);

        return Inertia::render('portal/events/show', [
            'event' => [
                ...$this->summarise($event, $alumni),
                'description' => $event->description,
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Register the signed-in alumni for the event.
     */
    public function register(Request $request, Event $event): RedirectResponse
    {
        $alumni = $this->alumni($request);

        abort_unless($event->status === 'published', 404);

        if ($event->starts_at->isPast()) {
            return back()->with('error', 'Cet evenement a deja eu lieu.');
        }

        try {
            $this->registrations->register($event, $alumni);
        } catch (RegistrationRefusedException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Votre inscription est enregistree.');
    }

    /**
     * Cancel the alumni's registration for the event.
     */
    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $alumni = $this->alumni($request);

        abort_unless($this->registrations->cancel($event, $alumni), 404);

        return back()->with('success', 'Votre inscription a ete annulee.');
    }

    /**
     * The event as the portal displays it, with the alumni's own status.
     *
     * @return array<string, mixed>
     */
    private function summarise(Event $event, Alumni $alumni): array
    {
        $registration = $event->alumni()
            ->wherePivot('alumni_id', $alumni->id)
            ->first();

        $status = $registration?->pivot->getAttribute('status');

        return [
            'id' => $event->id,
            'title' => $event->title,
            'location' => $event->location,
            'starts_at' => $event->starts_at->toIso8601String(),
            'capacity' => $event->capacity,
            'registrations_count' => (int) ($event->registrations_count ?? 0),
            'is_registered' => $status === 'registered',
            'attended' => (bool) $registration?->pivot->getAttribute('attended'),
            'is_past' => $event->starts_at->isPast(),
            'is_full' => $event->capacity !== null
                && (int) ($event->registrations_count ?? 0) >= $event->capacity,
        ];
    }

    /**
     * The alumni signed in on the portal guard.
     */
    private function alumni(Request $request): Alumni
    {
        $alumni = $request->user('alumni');

        abort_unless($alumni instanceof Alumni, 403);

        return $alumni;
    }
}
