<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A platform or organization account (docs/database-schema.md §5.8).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $role_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization|null $organization
 * @property-read Role|null $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id', 'role_id', 'name', 'email', 'password', 'is_active', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The role this user holds.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The Super Administrator is the account attached to no organization.
     */
    public function isSuperAdmin(): bool
    {
        return $this->organization_id === null;
    }

    /**
     * The flat list of permission slugs granted by this user's role.
     *
     * @return Collection<int, string>
     */
    public function permissionSlugs(): Collection
    {
        if ($this->relationLoaded('role') && $this->role?->relationLoaded('permissions')) {
            return $this->role->permissions->pluck('slug');
        }

        return Permission::query()
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $this->role_id)
            ->pluck('permissions.slug');
    }

    /**
     * Whether this user's role grants the permission.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissionSlugs()->contains($slug);
    }
}
