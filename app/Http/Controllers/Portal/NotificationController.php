<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Support\PortalNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The alumni's notifications (docs/project.md F10).
 */
class NotificationController extends Controller
{
    /**
     * List the alumni's notifications.
     */
    public function index(Request $request): Response
    {
        $alumni = $this->alumni($request);

        return Inertia::render('portal/notifications/index', [
            'notifications' => $alumni->notifications()
                ->latest()
                ->paginate(20)
                ->through(fn (DatabaseNotification $notification): array => PortalNotifications::present($notification)),
        ]);
    }

    /**
     * Mark one notification read.
     */
    public function update(Request $request, string $notification): RedirectResponse
    {
        $alumni = $this->alumni($request);

        $row = $alumni->notifications()->whereKey($notification)->first();

        abort_if($row === null, 404);

        $row->markAsRead();

        return back();
    }

    /**
     * Mark every notification of the alumni as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $this->alumni($request)->unreadNotifications->markAsRead();

        return back()->with('success', 'Toutes vos notifications sont marquees comme lues.');
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
