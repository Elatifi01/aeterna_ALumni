<?php

namespace App\Scopes;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Row-level tenant isolation (docs/project.md §4).
 *
 * @implements Scope<Model>
 */
class OrganizationScope implements Scope
{
    /**
     * Constrain the query to the current organization, when there is one.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = Tenant::organizationId();

        if ($organizationId === null) {
            return;
        }

        $builder->where($model->getTable().'.organization_id', $organizationId);
    }
}
