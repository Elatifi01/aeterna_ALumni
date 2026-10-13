<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn of a conversation (docs/project.md F14).
 *
 * @property int $id
 * @property int $ai_conversation_id
 * @property string $role
 * @property string|null $content
 * @property string|null $tool_name
 * @property array<string, mixed>|null $tool_arguments
 * @property array<string, mixed>|null $tool_result
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AiConversation|null $conversation
 */
class AiMessage extends Model
{
    protected $fillable = [
        'role', 'content', 'tool_name', 'tool_arguments', 'tool_result',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_arguments' => 'array',
            'tool_result' => 'array',
        ];
    }

    /**
     * The conversation this message is part of.
     *
     * @return BelongsTo<AiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
