<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Pivots\AlumniCampaign;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An email campaign (docs/database-schema.md §5.12).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by
 * @property string $subject
 * @property string $content
 * @property string $status
 * @property int $recipients_count
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization|null $organization
 * @property-read AlumniCampaign $pivot
 * @property-read int|null $sent_count
 * @property-read int|null $failed_count
 */
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by', 'subject', 'content', 'status', 'recipients_count', 'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    /**
     * The user who created the campaign.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The alumni the campaign is addressed to, with their delivery status.
     *
     * @return BelongsToMany<Alumni, $this, AlumniCampaign>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Alumni::class, 'alumni_campaign')
            ->using(AlumniCampaign::class)
            ->withPivot(['id', 'status', 'sent_at', 'error_message'])
            ->withTimestamps();
    }
}
