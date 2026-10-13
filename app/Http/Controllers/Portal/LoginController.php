<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Organization;
use App\Services\AlumniAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Signing in to the alumni portal (docs/project.md F9).
 */
class LoginController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly AlumniAccountService $accounts) {}

    /**
     * Show the portal login form for the organization.
     */
    public function create(Organization $organization): Response
    {
        $this->assertOrganizationServes($organization);

        if (! $this->accounts->isAvailableTo($organization)) {
            return Inertia::render('portal/auth/unavailable', [
                'organization' => $this->brand($organization),
            ]);
        }

        return Inertia::render('portal/auth/login', [
            'organization' => $this->brand($organization),
            'status' => session('status'),
        ]);
    }

    /**
     * Attempt to sign the alumni in.
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->assertOrganizationServes($organization);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->accounts->isAvailableTo($organization)) {
            throw ValidationException::withMessages([
                'email' => __('Le portail alumni n est pas disponible pour cette organisation.'),
            ]);
        }

        $this->assertNotRateLimited($request, $organization);

        $attempted = Auth::guard('alumni')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'organization_id' => $organization->id,
        ], $request->boolean('remember'));

        if (! $attempted) {
            RateLimiter::hit($this->throttleKey($request, $organization));

            throw ValidationException::withMessages([
                'email' => __('Ces identifiants ne correspondent a aucun compte.'),
            ]);
        }

        $alumni = Auth::guard('alumni')->user();

        if (! $alumni instanceof Alumni) {
            throw ValidationException::withMessages([
                'email' => __('Ces identifiants ne correspondent a aucun compte.'),
            ]);
        }

        if ($this->initialPasswordHasExpired($alumni)) {
            Auth::guard('alumni')->logout();

            throw ValidationException::withMessages([
                'email' => __('Ce mot de passe provisoire a expire. Utilisez « Mot de passe oublie » pour en choisir un nouveau.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $organization));

        $request->session()->regenerate();

        $request->session()->put('portal_organization', $organization->slug);

        $alumni->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('portal.dashboard'));
    }

    /**
     * Sign the alumni out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $alumni = Auth::guard('alumni')->user();
        $slug = $alumni instanceof Alumni
            ? $alumni->organization()->withoutGlobalScopes()->value('slug')
            : null;

        Auth::guard('alumni')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $slug === null
            ? redirect('/')
            : redirect()->route('portal.login', ['organization' => $slug]);
    }

    /**
     * A deactivated organization has no portal at all. 404 rather than 403: whether a given slug exists is not something an anonymous visitor needs to be able to tell.
     */
    private function assertOrganizationServes(Organization $organization): void
    {
        abort_unless($organization->is_active, 404);
    }

    /**
     * A provisional password stops working once it has sat unused too long.
     */
    private function initialPasswordHasExpired(Alumni $alumni): bool
    {
        $days = config('portal.initial_password_expires_days');

        if ($days === null || ! $alumni->must_change_password) {
            return false;
        }

        return $alumni->credentials_sent_at !== null
            && $alumni->credentials_sent_at->addDays((int) $days)->isPast();
    }

    /**
     * Refuse the attempt when this e-mail, organization and address have tried too often.
     */
    private function assertNotRateLimited(Request $request, Organization $organization): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $organization), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $organization));

        throw ValidationException::withMessages([
            'email' => __('Trop de tentatives. Reessayez dans :seconds secondes.', ['seconds' => $seconds]),
        ]);
    }

    /**
     * Keyed on the organization as well as the address, mirroring the staff rule in FortifyServiceProvider: the same e-mail in two establishments is two different accounts and must not share a budget of attempts.
     */
    private function throttleKey(Request $request, Organization $organization): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$organization->id.'|'.$request->ip(),
        );
    }

    /**
     * What the anonymous screens show: the organization's own name and logo, falling back to the Aeterna mark (docs/design.md §2.2.1).
     *
     * @return array<string, mixed>
     */
    private function brand(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'logo_url' => $organization->logo_url,
        ];
    }
}
