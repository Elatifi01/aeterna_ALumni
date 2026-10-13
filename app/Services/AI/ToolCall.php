<?php

namespace App\Services\AI;

/**
 * The model asking for a tool to be run (docs/project.md F14).
 */
final class ToolCall
{
    /**
     * Create a new instance.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {}
}
