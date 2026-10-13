<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TalksToAssistant;
use App\Models\AiConversation;
use App\Services\AI\Actor;
use App\Services\AI\Assistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aeterna Assistant, in the staff space (docs/project.md F14).
 */
class AssistantController extends Controller
{
    use TalksToAssistant;

    /**
     * Create a new instance.
     */
    public function __construct(private readonly Assistant $assistant) {}

    /**
     * The member of staff signed in on the web guard, or null.
     */
    protected function resolveActor(Request $request): ?Actor
    {
        return Actor::fromStaff($request);
    }

    /**
     * Who may open Aeterna Assistant at all (docs/project.md §5.4).
     */
    private function assistantActorAllowed(Request $request): Actor
    {
        $actor = $this->assistantActor($request);

        abort_unless($actor->can('assistant.use'), 403);

        return $actor;
    }

    /**
     * Show the assistant with the requested or latest conversation.
     */
    public function index(Request $request, ?AiConversation $conversation = null): Response
    {
        $actor = $this->assistantActorAllowed($request);

        $current = $conversation?->exists === true
            ? $this->conversationOrFail($actor, $conversation->id)
            : ($request->boolean('new') ? null : $this->conversationsOf($actor)->latest('updated_at')->first());

        return Inertia::render('assistant/index', [
            'conversations' => $this->conversationList($actor),
            'conversation' => $current === null ? null : [
                'id' => $current->id,
                'title' => $current->title,
            ],
            'messages' => $current === null ? [] : $this->transcript($current),
            'suggestions' => $this->suggestionsFor($actor),
        ]);
    }

    /**
     * Ask the assistant a question and return to the conversation.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $this->assistantActorAllowed($request);

        $conversation = $this->askAssistant($actor, $this->validateQuestion($request), $this->assistant);

        return to_route('assistant.show', $conversation);
    }

    /**
     * Delete one of the user's conversations.
     */
    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        $actor = $this->assistantActorAllowed($request);

        $this->conversationOrFail($actor, $conversation->id)->delete();

        return to_route('assistant.index')->with('success', 'Conversation supprimee.');
    }

    /**
     * Suggested questions, limited to what this user's permissions could answer.
     *
     * @return list<string>
     */
    private function suggestionsFor(Actor $actor): array
    {
        $suggestions = [];

        if ($actor->can('alumni.view')) {
            $suggestions[] = 'Combien d alumni travaillent dans l informatique ?';
            $suggestions[] = 'Quelles villes comptent le plus d alumni ?';
            $suggestions[] = 'Trouve les alumni de la promotion 2023';
            $suggestions[] = 'Quelles sont les entreprises les plus representees ?';
        }

        if ($actor->can('events.view')) {
            $suggestions[] = 'Quels evenements sont prevus ?';
        }

        if ($actor->can('campaigns.create')) {
            $suggestions[] = 'Redige une campagne pour inviter les alumni au prochain evenement';
        }

        return $suggestions === []
            ? ['Que peux-tu faire ?']
            : $suggestions;
    }
}
