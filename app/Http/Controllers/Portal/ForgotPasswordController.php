<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\AlumniPasswordResetMail;
use App\Models\Alumni;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mot de passe oublie" for the portal (docs/project.md F9).
 */
class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot-password form.
     */
    public function create(Organization $organization): Response
    {
        abort_unless($organization->is_active, 404);

        return Inertia::render('portal/auth/forgot-password', [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'logo_url' => $organization->logo_url,
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * Send a reset link when the e-mail matches an account of this organization.
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->is_active, 404);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $alumni = Alumni::acrossOrganizations()
            ->where('organization_id', $organization->id)
            ->where('email', $validated['email'])
            ->first();

        if ($alumni !== null && $alumni->has_account) {
            $token = Str::random(64);

            $alumni->forceFill([
                'login_token' => hash('sha256', $token),
                'login_token_expires_at' => now()->addMinutes(
                    (int) config('portal.reset_link_expires_minutes', 60),
                ),
            ])->save();

            Mail::to($alumni->email)->queue(
                new AlumniPasswordResetMail($alumni, $organization, $token),
            );
        }

        return back()->with('status', 'Si un compte correspond a cette adresse, un lien vient de vous etre envoye.');
    }

    /**
     * Show the reset form for a valid token.
     */
    public function edit(Request $request, Organization $organization, string $token): Response
    {
        abort_unless($organization->is_active, 404);

        return Inertia::render('portal/auth/reset-password', [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'logo_url' => $organization->logo_url,
            ],
            'token' => $token,
            'email' => $request->string('email')->value(),
        ]);
    }

    /**
     * Set the new password and sign the alumni in.
     */
    public function update(Request $request, Organization $organization, string $token): RedirectResponse
    {
        abort_unless($organization->is_active, 404);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        $alumni = Alumni::acrossOrganizations()
            ->where('organization_id', $organization->id)
            ->where('email', $validated['email'])
            ->where('login_token', hash('sha256', $token))
            ->first();

        if ($alumni === null || $alumni->login_token_expires_at === null || $alumni->login_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'email' => __('Ce lien n est plus valide. Demandez-en un nouveau.'),
            ]);
        }

        if (Hash::check($validated['password'], (string) $alumni->password)) {
            throw ValidationException::withMessages([
                'password' => __('Choisissez un mot de passe different du precedent.'),
            ]);
        }

        $alumni->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
            'login_token' => null,
            'login_token_expires_at' => null,
            'remember_token' => null,
        ])->save();

        return redirect()
            ->route('portal.login', ['organization' => $organization->slug])
            ->with('status', 'Votre mot de passe a ete enregistre. Vous pouvez vous connecter.');
    }
}
