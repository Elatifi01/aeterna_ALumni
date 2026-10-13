<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The portal message inbox (docs/project.md F10).
 */
class MessageController extends Controller
{
    /**
     * List the campaigns sent to the alumni.
     */
    public function index(Request $request): Response
    {
        $alumni = $this->alumni($request);

        $messages = $alumni->campaigns()
            ->wherePivot('status', 'sent')
            ->orderByPivot('sent_at', 'desc')
            ->paginate(15)
            ->through(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'excerpt' => str($campaign->content)->stripTags()->limit(140)->value(),
                'sent_at' => $campaign->pivot->getAttribute('sent_at'),
                'read' => $campaign->pivot->getAttribute('read_at') !== null,
            ]);

        return Inertia::render('portal/messages/index', [
            'messages' => $messages,
        ]);
    }

    /**
     * Show one message and mark it read.
     */
    public function show(Request $request, Campaign $campaign): Response
    {
        $alumni = $this->alumni($request);

        $row = $alumni->campaigns()
            ->wherePivot('campaign_id', $campaign->id)
            ->wherePivot('status', 'sent')
            ->first();

        abort_if($row === null, 404);

        if ($row->pivot->getAttribute('read_at') === null) {
            $alumni->campaigns()->updateExistingPivot($campaign->id, ['read_at' => now()]);
        }

        return Inertia::render('portal/messages/show', [
            'message' => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'content' => $campaign->content,
                'sent_at' => $row->pivot->getAttribute('sent_at'),
            ],
        ]);
    }

    /**
     * Mark every message of the alumni as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $alumni = $this->alumni($request);

        $unread = $alumni->campaigns()
            ->wherePivot('status', 'sent')
            ->wherePivotNull('read_at')
            ->pluck('campaigns.id');

        foreach ($unread as $id) {
            $alumni->campaigns()->updateExistingPivot($id, ['read_at' => now()]);
        }

        return back()->with('success', 'Tous vos messages sont marques comme lus.');
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
