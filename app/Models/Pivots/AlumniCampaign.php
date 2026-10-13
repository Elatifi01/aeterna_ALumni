<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Per-recipient delivery status (docs/database-schema.md §5.13).
 *
 * @property int $id
 * @property int $alumni_id
 * @property int $campaign_id
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $read_at
 * @property string|null $error_message
 */
class AlumniCampaign extends Pivot
{
    protected $table = 'alumni_campaign';

    public $incrementing = true;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
