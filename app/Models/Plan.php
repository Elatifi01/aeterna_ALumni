<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Commercial offer (docs/database-schema.md §5.2).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $price_monthly
 * @property string $price_yearly
 * @property int|null $max_alumni
 * @property int|null $max_events
 * @property int|null $max_campaigns
 * @property int|null $max_users
 * @property bool $alumni_portal
 * @property string|null $paddle_price_monthly_id
 * @property string|null $paddle_price_yearly_id
 * @property bool $is_active
 * @property bool $is_custom
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $active_subscriptions_count
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_yearly',
        'max_alumni', 'max_events', 'max_campaigns', 'max_users',
        'alumni_portal',
        'paddle_price_monthly_id', 'paddle_price_yearly_id',
        'is_active', 'is_custom',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'max_alumni' => 'integer',
            'max_events' => 'integer',
            'max_campaigns' => 'integer',
            'max_users' => 'integer',
            'alumni_portal' => 'boolean',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    /**
     * Plans on the price list: the ones a Super Administrator can assign to any organization, as opposed to a bespoke plan built for exactly one.
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_custom', false);
    }

    /**
     * The subscriptions on this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The free plan is not sold: it is the demo trial the Super Administrator grants for a set number of days.
     */
    public function isFree(): bool
    {
        return (float) $this->price_monthly <= 0 && (float) $this->price_yearly <= 0;
    }

    /**
     * Whether a visitor can buy this plan from the landing page: offered to everyone, switched on, and not the free demo trial.
     */
    public function isSelfServe(): bool
    {
        return ! $this->is_custom && $this->is_active && ! $this->isFree();
    }

    /**
     * Price for a billing cycle, as stored on the subscription.
     */
    public function priceFor(string $billingCycle): string
    {
        return $billingCycle === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }

    /**
     * Paddle price identifier for a billing cycle (docs/database-schema.md §4.2).
     */
    public function paddlePriceIdFor(string $billingCycle): ?string
    {
        return $billingCycle === 'yearly'
            ? $this->paddle_price_yearly_id
            : $this->paddle_price_monthly_id;
    }
}
