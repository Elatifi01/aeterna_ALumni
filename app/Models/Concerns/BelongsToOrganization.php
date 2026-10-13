<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Scopes\OrganizationScope;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied by every tenant-owned model (docs/database-schema.md §7).
 *
 * @property int|null $organization_id
 * @property-read Organization|null $organization
 *
 * @phpstan-require-extends Model
 */
trait BelongsToOrganization
{
    /**
     * Apply the organization scope and fill organization_id on create.
     */
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', Tenant::organizationId());
            }
        });
    }

    /**
     * The organization this record belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query without the tenant filter.
     *
     * @return Builder<static>
     */
    public static function acrossOrganizations(): Builder
    {
        return static::query()->withoutGlobalScope(OrganizationScope::class);
    }
}
