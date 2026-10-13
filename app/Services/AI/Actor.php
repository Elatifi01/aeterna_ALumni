<?php

namespace App\Services\AI;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Who is asking the assistant (docs/project.md F14).
 */
final class Actor
{
    /**
     * Create a new instance.
     */
    private function __construct(
        public readonly ?Alumni $alumni,
        public readonly ?User $user,
        public readonly Organization $organization,
    ) {}

    /**
     * The alumni signed in on the portal guard, or null.
     */
    public static function fromPortal(Request $request): ?self
    {
        $alumni = $request->user('alumni');

        if (! $alumni instanceof Alumni) {
            return null;
        }

        $organization = $alumni->organization()->withoutGlobalScopes()->first();

        return $organization === null
            ? null
            : new self(alumni: $alumni, user: null, organization: $organization);
    }

    /**
     * The member of staff signed in on the web guard, or null.
     */
    public static function fromStaff(Request $request): ?self
    {
        $user = $request->user('web');

        return $user instanceof User && $user->organization !== null
            ? new self(alumni: null, user: $user, organization: $user->organization)
            : null;
    }

    /**
     * Whether the actor is an alumni.
     */
    public function isAlumni(): bool
    {
        return $this->alumni !== null;
    }

    /**
     * Whether the actor is a member of staff.
     */
    public function isStaff(): bool
    {
        return $this->user !== null;
    }

    /**
     * Whether this actor holds a permission.
     */
    public function can(string $permission): bool
    {
        if ($this->alumni !== null) {
            return $this->alumni->hasPermission($permission);
        }

        return $this->user?->hasPermission($permission) === true;
    }

    /**
     * The record a conversation belongs to.
     */
    public function owner(): Model
    {
        return $this->alumni ?? $this->user;
    }

    /**
     * How to address them in the system prompt.
     */
    public function displayName(): string
    {
        return $this->alumni->full_name ?? (string) $this->user?->name;
    }
}
