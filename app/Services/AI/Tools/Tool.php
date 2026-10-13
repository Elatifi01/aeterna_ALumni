<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Actor;

/**
 * Something the assistant can do on the user's behalf (docs/project.md F14).
 */
interface Tool
{
    /**
     * The name the model calls it by.
     */
    public function name(): string;

    /**
     * What it does, written for the model to choose between tools.
     */
    public function description(): string;

    /**
     * JSON schema for the arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Whether this actor may use it at all.
     */
    public function authorize(Actor $actor): bool;

    /**
     * Run it and return the result the model will read.
     *
     * @param  array<string, mixed>  $arguments  the model's suggestion, unvalidated
     * @return array<string, mixed>
     */
    public function run(array $arguments, Actor $actor): array;
}
