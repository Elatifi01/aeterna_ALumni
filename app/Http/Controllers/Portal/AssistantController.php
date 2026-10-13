<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\TalksToAssistant;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\Actor;
use App\Services\AI\Assistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aeterna Assistant, in the alumni portal (docs/project.md F14).
 */
class AssistantController extends Controller
{
    use TalksToAssistant;

    /**
     * Create a new instance.
     */
    public function __construct(private readonly Assistant $assistant) {}

    /**
     * The alumni signed in on the portal guard, or null.
     */
    protected function resolveActor(Request $request): ?Actor
    {
        return Actor::fromPortal($request);
    }

    /**
     * Show the assistant with the requested or latest conversation.
     */
    public function index(Request $request, ?AiConversation $conversation = null): Response
    {
        $actor = $this->assistantActor($request);

        $current = $conversation?->exists === true
            ? $this->conversationOrFail($actor, $conversation->id)
            : ($request->boolean('new') ? null : $this->conversationsOf($actor)->latest('updated_at')->first());

        return Inertia::render('portal/assistant/index', [
            'conversations' => $this->conversationList($actor),
            'conversation' => $current === null ? null : [
                'id' => $current->id,
                'title' => $current->title,
            ],
            'messages' => $current === null ? [] : $this->transcript($current),
            'suggestions' => [
                'Quels evenements sont prevus ?',
                'Trouve-moi des offres de developpeur a Casablanca',
                'Quelles offres correspondent a mon profil ?',
                'Ou en sont mes candidatures ?',
                'Quelles competences developper pour evoluer ?',
                'Comment mettre a jour mon profil ?',
            ],
        ]);
    }

    /**
     * Ask the assistant a question and return to the conversation.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $this->assistantActor($request);

        $conversation = $this->askAssistant($actor, $this->validateQuestion($request), $this->assistant);

        return to_route('portal.assistant.show', $conversation);
    }

    /**
     * Delete one of the alumni's conversations.
     */
    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        $actor = $this->assistantActor($request);

        $this->conversationOrFail($actor, $conversation->id)->delete();

        return to_route('portal.assistant.index')->with('success', 'Conversation supprimee.');
    }
}
