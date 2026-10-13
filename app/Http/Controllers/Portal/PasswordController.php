<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The forced first password change (docs/project.md F9).
 */
class PasswordController extends Controller
{
    /**
     * Show the forced first-login password change.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $alumni = $request->user('alumni');

        if ($alumni instanceof Alumni && ! $alumni->must_change_password) {
            return redirect()->route('portal.dashboard');
        }

        return Inertia::render('portal/auth/change-password');
    }

    /**
     * Set the first password and clear the forced change.
     */
    public function update(Request $request): RedirectResponse
    {
        $alumni = $request->user('alumni');

        abort_unless($alumni instanceof Alumni, 403);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        if (Hash::check($validated['password'], (string) $alumni->password)) {
            throw ValidationException::withMessages([
                'password' => __('Choisissez un mot de passe different de celui recu par e-mail.'),
            ]);
        }

        $alumni->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
            'login_token' => null,
            'login_token_expires_at' => null,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('success', 'Votre mot de passe a ete enregistre.');
    }

    /**
     * The signed-in alumni changing their password again, from the portal's own settings.
     */
    public function editSettings(): Response
    {
        return Inertia::render('portal/settings/password');
    }

    /**
     * Change the password from the settings screen.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $alumni = $request->user('alumni');

        abort_unless($alumni instanceof Alumni, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        if (! Hash::check($validated['current_password'], (string) $alumni->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('Ce mot de passe est incorrect.'),
            ]);
        }

        $alumni->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();

        return back()->with('success', 'Votre mot de passe a ete modifie.');
    }
}
