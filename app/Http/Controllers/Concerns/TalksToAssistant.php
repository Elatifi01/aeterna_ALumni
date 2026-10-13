<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiConversation;
use App\Services\AI\Actor;
use App\Services\AI\Assistant;
use App\Services\AI\AssistantUnavailable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Everything the portal assistant and the staff assistant do identically (docs/project.md F14).
 */
trait TalksToAssistant
{
    /**
     * Ask a question and record both sides.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function askAssistant(Actor $actor, array $validated, Assistant $assistant): AiConversation
    {
        $conversation = $this->resolveConversation($actor, $validated);

        if ($this->jobSearchesSpent($conversation) >= $this->jobSearchCap()) {
            $conversation->messages()->create(['role' => 'user', 'content' => $validated['message']]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Vous avez atteint le nombre de recherches d offres pour cette conversation. '
                    .'Ouvrez une nouvelle conversation pour en relancer.',
            ]);

            return $conversation;
        }

        try {
            $assistant->reply($conversation, $actor, (string) $validated['message']);
        } catch (AssistantUnavailable $exception) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $exception->getMessage(),
            ]);
        }

        $conversation->touch();

        return $conversation;
    }

    /**
     * The conversation named in the request, or a new one owned by the actor.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveConversation(Actor $actor, array $validated): AiConversation
    {
        $id = $validated['conversation_id'] ?? null;

        if (is_int($id)) {
            return $this->conversationOrFail($actor, $id);
        }

        $conversation = new AiConversation;
        $conversation->organization_id = $actor->organization->id;
        $conversation->owner()->associate($actor->owner());
        $conversation->title = AiConversation::titleFrom((string) $validated['message']);
        $conversation->save();

        return $conversation;
    }

    /**
     * A conversation of this actor's, or 404.
     */
    protected function conversationOrFail(Actor $actor, int $id): AiConversation
    {
        $conversation = $this->conversationsOf($actor)->whereKey($id)->first();

        abort_if($conversation === null, 404);

        return $conversation;
    }

    /**
     * The query for the conversations this actor owns.
     *
     * @return Builder<AiConversation>
     */
    protected function conversationsOf(Actor $actor): Builder
    {
        $owner = $actor->owner();

        return AiConversation::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    /**
     * The conversation list for the sidebar.
     *
     * @return list<array<string, mixed>>
     */
    protected function conversationList(Actor $actor): array
    {
        $rows = [];

        foreach ($this->conversationsOf($actor)->latest('updated_at')->limit(30)->get() as $one) {
            $rows[] = [
                'id' => $one->id,
                'title' => $one->title,
                'updated_at' => $one->updated_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * The exchange as the screen shows it.
     *
     * @return list<array<string, mixed>>
     */
    protected function transcript(AiConversation $conversation): array
    {
        $rows = [];

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get();

        foreach ($messages as $message) {
            $rows[] = [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'tools_used' => is_array($message->tool_result['tools_used'] ?? null)
                    ? array_values($message->tool_result['tools_used'])
                    : [],
                'created_at' => $message->created_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * Validate the question and the optional conversation id.
     *
     * @return array<string, mixed>
     */
    protected function validateQuestion(Request $request): array
    {
        return $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ], [], ['message' => 'message']);
    }

    /**
     * Who is asking, resolved from the guard THIS SPACE authenticates on.
     */
    abstract protected function resolveActor(Request $request): ?Actor;

    /**
     * The actor for this request, or 403 when nobody usable is signed in.
     */
    protected function assistantActor(Request $request): Actor
    {
        $actor = $this->resolveActor($request);

        abort_if($actor === null, 403);

        return $actor;
    }

    /**
     * How many job searches this conversation has already run.
     */
    private function jobSearchesSpent(AiConversation $conversation): int
    {
        return $conversation->messages()
            ->where('role', 'tool')
            ->where('tool_name', 'search_jobs')
            ->count();
    }

    /**
     * The maximum job searches allowed per conversation.
     */
    private function jobSearchCap(): int
    {
        return max(1, (int) config('services.ai.job_searches_per_conversation', 5));
    }
}
