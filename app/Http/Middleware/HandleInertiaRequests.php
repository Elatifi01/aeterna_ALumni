<?php

namespace App\Http\Middleware;

use App\Support\PortalNotifications;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $inPortal = $request->is('portail', 'portail/*');

        $alumni = $inPortal ? $request->user('alumni') : null;
        $user = $inPortal ? null : $request->user('web');

        $organization = $alumni !== null
            ? $alumni->organization()->withoutGlobalScopes()->first()
            : ($user?->isSuperAdmin() === false ? $user->organization : null);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'alumni' => $alumni === null ? null : [
                    'id' => $alumni->id,
                    'first_name' => $alumni->first_name,
                    'last_name' => $alumni->last_name,
                    'full_name' => $alumni->full_name,
                    'email' => $alumni->email,
                    'must_change_password' => $alumni->must_change_password,
                    'unread_notifications' => fn (): int => $alumni->unreadNotifications()->count(),
                    'recent_notifications' => fn (): array => PortalNotifications::recentFor($alumni),
                    'unread_messages' => fn (): int => $alumni->campaigns()
                        ->wherePivot('status', 'sent')
                        ->wherePivotNull('read_at')
                        ->count(),
                    'permissions' => $alumni->portalPermissions(),
                ],
                'organization' => $organization === null ? null : [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'logo_url' => $organization->logo_url,
                    'plan_name' => $organization->activeSubscription?->plan?->name,
                ],
                'permissions' => $user?->permissionSlugs()->values()->all() ?? [],
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
            ],
            'flash' => Inertia::always(fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
