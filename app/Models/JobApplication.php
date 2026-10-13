<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer an alumni saved or applied to (docs/project.md F11).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $alumni_id
 * @property string $external_id
 * @property string $title
 * @property string|null $company
 * @property string|null $location
 * @property string|null $source
 * @property string $link
 * @property string $status
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Alumni|null $alumni
 * @property-read Organization|null $organization
 */
class JobApplication extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'external_id', 'title', 'company', 'location',
        'source', 'link', 'status', 'applied_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    /**
     * The alumni who saved or applied to the offer.
     *
     * @return BelongsTo<Alumni, $this>
     */
    public function alumni(): BelongsTo
    {
        return $this->belongsTo(Alumni::class);
    }
}
