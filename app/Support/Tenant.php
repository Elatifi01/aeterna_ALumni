<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Which organization the current request belongs to (docs/project.md §4).
 */
final class Tenant
{
    /** @var list<string> */
    public const GUARDS = ['web', 'alumni'];

    /**
     * The current organization's id, or null when no tenant applies.
     */
    public static function organizationId(): ?int
    {
        foreach (self::guards() as $guard) {
            if (! Auth::guard($guard)->hasUser()) {
                continue;
            }

            $organizationId = Auth::guard($guard)->user()?->getAttribute('organization_id');

            if ($organizationId !== null) {
                return (int) $organizationId;
            }
        }

        return null;
    }

    /**
     * The guards in the order THIS request should be asked.
     *
     * @return list<string>
     */
    private static function guards(): array
    {
        return self::isPortalRequest() ? ['alumni', 'web'] : ['web', 'alumni'];
    }

    /**
     * Whether the current request is a portal request.
     */
    private static function isPortalRequest(): bool
    {
        return app()->bound('request') && request()->is('portail', 'portail/*');
    }
}
