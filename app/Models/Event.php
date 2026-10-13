<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Pivots\AlumniEvent;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An event organized by an organization (docs/database-schema.md §5.10).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $capacity
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization|null $organization
 * @property-read AlumniEvent $pivot
 * @property-read int|null $registrations_count
 * @property-read int|null $attendees_count
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by', 'title', 'description', 'location',
        'starts_at', 'ends_at', 'capacity', 'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    /**
     * The user who created the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The alumni registered for the event, with attendance.
     *
     * @return BelongsToMany<Alumni, $this, AlumniEvent>
     */
    public function alumni(): BelongsToMany
    {
        return $this->belongsToMany(Alumni::class, 'alumni_event')
            ->using(AlumniEvent::class)
            ->withPivot(['id', 'status', 'registered_at', 'attended', 'attended_at'])
            ->withTimestamps();
    }

    /**
     * Alumni still registered, i.e. excluding cancelled registrations.
     *
     * @return BelongsToMany<Alumni, $this, AlumniEvent>
     */
    public function registrations(): BelongsToMany
    {
        return $this->alumni()->wherePivot('status', 'registered');
    }
}
