<?php

namespace App\Http\Middleware;

use App\Models\Alumni;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The forced first password change (docs/project.md F9).
 */
class EnsureAlumniPasswordChanged
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $alumni = Auth::guard('alumni')->user();

        if (! $alumni instanceof Alumni || ! $alumni->must_change_password) {
            return $next($request);
        }

        return redirect()->route('portal.password.change');
    }
}
