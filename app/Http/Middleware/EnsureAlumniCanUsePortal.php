<?php

namespace App\Http\Middleware;

use App\Models\Alumni;
use App\Services\AlumniAccountService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door to the alumni portal (docs/project.md F9).
 */
class EnsureAlumniCanUsePortal
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly AlumniAccountService $accounts) {}

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $alumni = Auth::guard('alumni')->user();

        if (! $alumni instanceof Alumni) {
            abort(403);
        }

        $organization = $alumni->organization()->withoutGlobalScopes()->first();

        if (! $alumni->portal_access) {
            return $this->reject($request, $organization?->slug, 'Votre acces au portail a ete suspendu.');
        }

        if ($organization === null || ! $organization->is_active) {
            return $this->reject($request, $organization?->slug, 'Cette organisation est desactivee.');
        }

        if (! $this->accounts->isAvailableTo($organization)) {
            return $this->reject(
                $request,
                $organization->slug,
                'Le portail alumni n est plus disponible pour cette organisation.',
            );
        }

        return $next($request);
    }

    /**
     * Sign the alumni out and send them back to their login with the reason.
     */
    private function reject(Request $request, ?string $slug, string $message): Response
    {
        Auth::guard('alumni')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($slug === null) {
            abort(403, $message);
        }

        return redirect()
            ->route('portal.login', ['organization' => $slug])
            ->withErrors(['email' => __($message)]);
    }
}
