<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the organization space (docs/project.md §4).
 */
class EnsureUserHasOrganization
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user === null) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        abort_unless($user->organization?->is_active === true, 403, __('Cette organisation est desactivee.'));

        return $next($request);
    }
}
