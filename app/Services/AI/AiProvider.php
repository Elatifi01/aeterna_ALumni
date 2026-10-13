<?php

namespace App\Services\AI;

/**
 * A model that can hold a conversation and ask for tools (docs/project.md F14).
 *
 * @phpstan-type Message array{role: string, content: string, tool_name?: string}
 */
interface AiProvider
{
    /**
     * Send the messages and tool definitions to the model and return its reply.
     *
     * @param  list<array<string, mixed>>  $messages  the conversation so far
     * @param  list<array<string, mixed>>  $tools  JSON-schema definitions
     *
     * @throws AssistantUnavailable
     */
    public function chat(array $messages, array $tools = []): AiReply;

    /**
     * Whether the provider is configured well enough to be worth calling.
     */
    public function isConfigured(): bool;
}
