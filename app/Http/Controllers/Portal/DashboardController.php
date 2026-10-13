<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The alumni's own landing screen (docs/project.md F9).
 */
class DashboardController extends Controller
{
    /**
     * Show the alumni's portal dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $alumni = $request->user('alumni');

        abort_unless($alumni instanceof Alumni, 403);

        return Inertia::render('portal/dashboard', [
            'alumni' => [
                'first_name' => $alumni->first_name,
                'full_name' => $alumni->full_name,
            ],
            'stats' => [
                'upcoming' => Event::query()
                    ->where('status', 'published')
                    ->where('starts_at', '>=', now())
                    ->count(),
                'registered' => $alumni->events()
                    ->wherePivot('status', 'registered')
                    ->where('starts_at', '>=', now())
                    ->count(),
                'attended' => $alumni->events()
                    ->wherePivot('attended', true)
                    ->count(),
            ],
            'nextEvents' => Event::query()
                ->where('status', 'published')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'location' => $event->location,
                    'starts_at' => $event->starts_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }
}
