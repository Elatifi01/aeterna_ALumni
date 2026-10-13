<?php

namespace App\Services\AI;

/**
 * One answer from the model (docs/project.md F14).
 */
final class AiReply
{
    /**
     * Create a new instance.
     *
     * @param  list<ToolCall>  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
    ) {}

    /**
     * Whether the model asked for tools to be run.
     */
    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
