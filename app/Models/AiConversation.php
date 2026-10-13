<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One conversation with the assistant (docs/project.md F14).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $title
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization|null $organization
 */
class AiConversation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['title'];

    /**
     * The alumni or user the conversation belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The custom error messages for the validation rules.
     *
     * @return HasMany<AiMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    /**
     * What the person sees in the list.
     */
    public static function titleFrom(string $question): string
    {
        return Str::limit(Str::squish($question), 100) ?: 'Nouvelle conversation';
    }
}
