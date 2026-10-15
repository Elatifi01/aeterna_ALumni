<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A platform route behind one of the Super Administrator role's permissions. Gate::before lets the Super Administrator past every policy, so this reads the role directly.
 */
class EnsureSuperAdminHasPermission
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(
            $request->user('web')?->hasPermission($permission) === true,
            403,
            'Cette partie de la plateforme est fermee pour votre role.',
        );

        return $next($request);
    }
}
