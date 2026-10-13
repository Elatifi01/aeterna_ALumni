<?php

namespace App\Http\Middleware;

use App\Models\Alumni;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A portal route behind one of the alumni role's permissions (docs/project.md F9, §5.4).
 */
class EnsureAlumniHasPermission
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $alumni = $request->user('alumni');

        abort_unless(
            $alumni instanceof Alumni && $alumni->hasPermission($permission),
            403,
            'Votre etablissement n a pas ouvert cette partie du portail.',
        );

        return $next($request);
    }
}
