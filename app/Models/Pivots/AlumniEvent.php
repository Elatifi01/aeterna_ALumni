<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Registration and attendance (docs/database-schema.md §5.11).
 *
 * @property int $id
 * @property int $alumni_id
 * @property int $event_id
 * @property string $status
 * @property Carbon|null $registered_at
 * @property bool $attended
 * @property Carbon|null $attended_at
 */
class AlumniEvent extends Pivot
{
    protected $table = 'alumni_event';

    public $incrementing = true;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'attended' => 'boolean',
            'attended_at' => 'datetime',
        ];
    }
}
