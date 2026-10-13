<?php

namespace App\Http\Controllers;

use App\Exceptions\RegistrationRefusedException;
use App\Models\Alumni;
use App\Models\Event;
use App\Services\EventRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Event registrations and attendance (docs/project.md F4).
 */
class EventRegistrationController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly EventRegistrationService $registrations) {}

    /**
     * Register an alumni for an event.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);

        $validated = $request->validate([
            'alumni_id' => [
                'required',
                Rule::exists('alumni', 'id')
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $alumni = Alumni::query()->findOrFail((int) $validated['alumni_id']);

        try {
            $this->registrations->register($event, $alumni);
        } catch (RegistrationRefusedException $exception) {
            throw ValidationException::withMessages([
                'alumni_id' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Alumni inscrit a l evenement.');
    }

    /**
     * Cancel a registration.
     */
    public function destroy(Event $event, Alumni $alumni): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);

        abort_unless($this->registrations->cancel($event, $alumni), 404);

        return back()->with('success', 'Inscription annulee.');
    }

    /**
     * Mark an alumni present or absent (docs/project.md F4).
     */
    public function attendance(Request $request, Event $event, Alumni $alumni): RedirectResponse
    {
        $this->authorize('manageRegistrations', $event);

        $validated = $request->validate([
            'attended' => ['required', 'boolean'],
        ]);

        $registration = $event->alumni()->wherePivot('alumni_id', $alumni->id)->first();

        abort_if($registration === null, 404);

        if ($registration->pivot->getAttribute('status') !== 'registered') {
            throw ValidationException::withMessages([
                'attended' => 'Cet alumni n est pas inscrit a cet evenement.',
            ]);
        }

        $event->alumni()->updateExistingPivot($alumni->id, [
            'attended' => $validated['attended'],
            'attended_at' => $validated['attended'] ? now() : null,
        ]);

        return back()->with(
            'success',
            $validated['attended'] ? 'Alumni marque present.' : 'Alumni marque absent.',
        );
    }
}
