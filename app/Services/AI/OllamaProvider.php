<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A local model, through Ollama (docs/project.md F14).
 */
class OllamaProvider implements AiProvider
{
    /**
     * Whether a base URL and model are configured.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->model() !== '';
    }

    /**
     * Call Ollama's chat endpoint and parse the reply.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     *
     * @throws AssistantUnavailable
     */
    public function chat(array $messages, array $tools = []): AiReply
    {
        if (! $this->isConfigured()) {
            throw new AssistantUnavailable;
        }

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => 0.3,
            ],
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout())
                ->post(rtrim($this->baseUrl(), '/').'/api/chat', $payload);
        } catch (ConnectionException) {
            throw new AssistantUnavailable;
        } catch (Throwable $exception) {
            Log::warning('Aeterna Assistant request failed.', ['reason' => class_basename($exception)]);

            throw new AssistantUnavailable;
        }

        if (! $response->successful()) {
            Log::warning('Aeterna Assistant responded badly.', ['status' => $response->status()]);

            throw new AssistantUnavailable(
                $response->status() === 404
                    ? 'Le modele d IA n est pas installe. Executez : ollama pull '.$this->model()
                    : null,
            );
        }

        $body = $response->json();

        if (! is_array($body) || ! is_array($body['message'] ?? null)) {
            Log::warning('Aeterna Assistant returned an unexpected body.');

            throw new AssistantUnavailable;
        }

        return new AiReply(
            content: is_string($body['message']['content'] ?? null)
                ? trim($body['message']['content'])
                : '',
            toolCalls: $this->toolCalls($body['message']['tool_calls'] ?? null),
        );
    }

    /**
     * Read the tool requests defensively.
     *
     * @return list<ToolCall>
     */
    private function toolCalls(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $calls = [];

        foreach ($raw as $call) {
            if (! is_array($call) || ! is_array($call['function'] ?? null)) {
                continue;
            }

            $name = $call['function']['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $arguments = $call['function']['arguments'] ?? [];

            if (is_string($arguments)) {
                $decoded = json_decode($arguments, true);
                $arguments = is_array($decoded) ? $decoded : [];
            }

            $calls[] = new ToolCall(
                name: $name,
                arguments: is_array($arguments) ? $arguments : [],
            );
        }

        return $calls;
    }

    /**
     * The Ollama base URL from configuration.
     */
    private function baseUrl(): string
    {
        return (string) config('services.ai.ollama.base_url', '');
    }

    /**
     * The model name from configuration.
     */
    private function model(): string
    {
        return (string) config('services.ai.ollama.model', '');
    }

    /**
     * The request timeout in seconds from configuration.
     */
    private function timeout(): int
    {
        return max(5, (int) config('services.ai.ollama.timeout', 120));
    }
}
