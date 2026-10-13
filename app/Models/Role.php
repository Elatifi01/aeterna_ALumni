<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\Permissions;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A named set of permissions (docs/database-schema.md §5.6).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 * @property string $guard
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization|null $organization
 * @property-read int|null $users_count
 * @property-read int|null $permissions_count
 */
class Role extends Model
{
    public const PLATFORM_SLUG = 'super-admin';

    /** @use HasFactory<RoleFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'is_system', 'guard'];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * The users holding this role.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Held by the organization's alumni rather than by its staff.
     */
    public function isAlumniRole(): bool
    {
        return $this->guard === 'alumni';
    }

    /**
     * The one role an organization's alumni hold, or null when the organization was never given one.
     */
    public static function alumniRoleFor(int $organizationId): ?self
    {
        return self::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('slug', Permissions::ALUMNI_ROLE_SLUG)
            ->where('guard', 'alumni')
            ->first();
    }

    /**
     * The permissions this role grants.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
