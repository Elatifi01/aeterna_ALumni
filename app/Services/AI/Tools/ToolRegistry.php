<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Actor;

/**
 * Every tool the assistant knows, and which of them an actor may use (docs/project.md F14).
 */
class ToolRegistry
{
    /**
     * Create a new instance.
     *
     * @param  iterable<Tool>  $tools
     */
    public function __construct(private readonly iterable $tools) {}

    /**
     * The tools this actor may use.
     *
     * @return list<Tool>
     */
    public function for(Actor $actor): array
    {
        $allowed = [];

        foreach ($this->tools as $tool) {
            if ($tool->authorize($actor)) {
                $allowed[] = $tool;
            }
        }

        return $allowed;
    }

    /**
     * One tool by name, or null when it does not exist or is not this actor's to use.
     */
    public function find(string $name, Actor $actor): ?Tool
    {
        foreach ($this->for($actor) as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * The definitions to send upstream.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(Actor $actor): array
    {
        return array_map(fn (Tool $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ], $this->for($actor));
    }
}
