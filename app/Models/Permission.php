<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An atomic permission, grouped by module (docs/project.md §5.4).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $module
 */
class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'module'];

    /**
     * The roles granting this permission.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
