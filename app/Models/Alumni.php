<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Pivots\AlumniCampaign;
use App\Models\Pivots\AlumniEvent;
use App\Support\Permissions;
use Database\Factories\AlumniFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A former student of an organization (docs/database-schema.md §5.9).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $import_id Which spreadsheet import created this row (F12)
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $password
 * @property bool $must_change_password
 * @property Carbon|null $credentials_sent_at
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 * @property bool $portal_access
 * @property string|null $login_token
 * @property Carbon|null $login_token_expires_at
 * @property string|null $phone
 * @property string|null $degree
 * @property int|null $graduation_year
 * @property string|null $company
 * @property string|null $job_title
 * @property string|null $city
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read bool $has_account
 * @property-read Organization|null $organization
 * @property-read AlumniEvent|AlumniCampaign $pivot Whichever pivot reached this row
 */
class Alumni extends Authenticatable
{
    /** @use HasFactory<AlumniFactory> */
    use BelongsToOrganization, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'alumni';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'degree',
        'graduation_year', 'company', 'job_title', 'city',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'login_token'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'portal_access' => 'boolean',
            'credentials_sent_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'login_token_expires_at' => 'datetime',
        ];
    }

    /** @var list<string>|null */
    private ?array $portalPermissions = null;

    /**
     * Whether this alumni may do something in the portal.
     */
    public function hasPermission(string $slug): bool
    {
        if (! str_starts_with($slug, 'portal.')) {
            return false;
        }

        return in_array($slug, $this->portalPermissions(), true);
    }

    /**
     * Reload the model and forget the cached portal permissions.
     */
    public function refresh(): static
    {
        $this->portalPermissions = null;

        return parent::refresh();
    }

    /**
     * The portal permission slugs this alumni holds, from their organization's alumni role.
     *
     * @return list<string>
     */
    public function portalPermissions(): array
    {
        if ($this->portalPermissions !== null) {
            return $this->portalPermissions;
        }

        $role = Role::alumniRoleFor((int) $this->organization_id);

        $this->portalPermissions = $role === null
            ? Permissions::portalSlugs()
            : array_values(array_filter(
                $role->permissions()->pluck('slug')->all(),
                fn (string $one): bool => str_starts_with($one, 'portal.'),
            ));

        return $this->portalPermissions;
    }

    /**
     * Whether this alumni has a portal account.
     */
    public function getHasAccountAttribute(): bool
    {
        return $this->password !== null;
    }

    /**
     * The events this alumni is registered for.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'alumni_event')
            ->withPivot(['status', 'registered_at', 'attended', 'attended_at'])
            ->withTimestamps();
    }

    /**
     * The campaigns addressed to this alumni.
     *
     * @return BelongsToMany<Campaign, $this>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'alumni_campaign')
            ->withPivot(['status', 'sent_at', 'read_at', 'error_message'])
            ->withTimestamps();
    }

    /**
     * First and last name together.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * The filters of the alumni list (docs/project.md F3/F13).
     *
     * @param  Builder<Alumni>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Alumni>
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->search(is_string($filters['search'] ?? null) ? $filters['search'] : null)
            ->when($filters['graduation_year'] ?? null, fn ($q, $year) => $q->where('graduation_year', $year))
            ->when($filters['degree'] ?? null, fn ($q, $degree) => $q->where('degree', $degree))
            ->when($filters['company'] ?? null, fn ($q, $company) => $q->where('company', $company));
    }

    /**
     * Free-text search across name, email and company (docs/project.md F3).
     *
     * @param  Builder<Alumni>  $query
     * @return Builder<Alumni>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_').'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('company', 'like', $like);
        });
    }
}
