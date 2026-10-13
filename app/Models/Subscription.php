<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An organization subscription to a plan (docs/database-schema.md §5.3).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $plan_id
 * @property string $billing_cycle
 * @property string $status
 * @property string $price
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Plan|null $plan
 * @property-read int|null $paid_count
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'plan_id', 'billing_cycle', 'status', 'price', 'starts_at', 'ends_at', 'canceled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * The plan subscribed to.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The payments made for this subscription.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Whether the subscription is active and within its dates.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->endOfDay()->isFuture();
    }
}
