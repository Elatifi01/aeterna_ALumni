<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateProfileRequest;
use App\Models\Alumni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An alumni's own profile (docs/project.md F9).
 */
class ProfileController extends Controller
{
    /**
     * Show the alumni's profile form.
     */
    public function edit(Request $request): Response
    {
        $alumni = $this->alumni($request);

        return Inertia::render('portal/settings/profile', [
            'profile' => [
                'phone' => $alumni->phone,
                'company' => $alumni->company,
                'job_title' => $alumni->job_title,
                'city' => $alumni->city,
            ],
            'locked' => [
                'first_name' => $alumni->first_name,
                'last_name' => $alumni->last_name,
                'email' => $alumni->email,
                'degree' => $alumni->degree,
                'graduation_year' => $alumni->graduation_year,
            ],
        ]);
    }

    /**
     * Update the alumni's own details.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $alumni = $this->alumni($request);

        $alumni->update($request->validated());

        return back()->with('success', 'Vos informations ont ete mises a jour.');
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
