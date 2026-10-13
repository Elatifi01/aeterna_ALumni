<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An administrator can deactivate an account (docs/project.md F2).
 */
class EnsureUserIsActive
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('web') !== null && ! $request->user('web')->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('Ce compte a ete desactive.')]);
        }

        return $next($request);
    }
}
