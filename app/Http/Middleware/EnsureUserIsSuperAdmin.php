<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /admin route group (docs/project.md §4, rule 4).
 */
class EnsureUserIsSuperAdmin
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('web')?->isSuperAdmin() === true, 403);

        return $next($request);
    }
}
