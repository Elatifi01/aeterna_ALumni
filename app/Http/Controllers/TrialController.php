<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an organization's staff land once its free demo trial is over.
 */
class TrialController extends Controller
{
    /**
     * Show the trial-ended page, or send back anyone whose space is open.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user('web');

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $organization = $user->organization;
        $trial = $organization?->trial();

        if ($organization === null || $trial === null || ! $organization->trialHasEnded()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('trial-ended', [
            'organization' => $organization->name,
            'endedOn' => $trial->ends_at->toDateString(),
            'contact' => config('aeterna.demo_request_recipient'),
        ]);
    }
}
