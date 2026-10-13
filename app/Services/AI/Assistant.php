<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The conversation loop (docs/project.md F14).
 */
class Assistant
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly AiProvider $provider,
        private readonly ToolRegistry $tools,
    ) {}

    /**
     * Answer one message, persisting both sides of the exchange.
     *
     * @throws AssistantUnavailable
     */
    public function reply(AiConversation $conversation, Actor $actor, string $question): AiMessage
    {
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        $messages = $this->history($conversation, $actor);
        $definitions = $this->tools->definitions($actor);

        $used = [];

        for ($iteration = 0; $iteration < $this->maxIterations(); $iteration++) {
            $reply = $this->provider->chat($messages, $definitions);

            if (! $reply->wantsTools()) {
                return $this->persist($conversation, $reply->content, $used);
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $reply->content,
                'tool_calls' => array_map(fn (ToolCall $call): array => [
                    'function' => ['name' => $call->name, 'arguments' => $call->arguments],
                ], $reply->toolCalls),
            ];

            foreach ($reply->toolCalls as $call) {
                $result = $this->execute($call, $actor, $conversation);

                $used[] = $call->name;

                $conversation->messages()->create([
                    'role' => 'tool',
                    'content' => '',
                    'tool_name' => $call->name,
                    'tool_arguments' => $call->arguments,
                    'tool_result' => $result,
                ]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_name' => $call->name,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ];
            }
        }

        return $this->persist(
            $conversation,
            'Je n ai pas reussi a repondre a cette question. Reformulez-la, ou posez-la plus simplement.',
            $used,
        );
    }

    /**
     * Run one requested tool.
     *
     * @return array<string, mixed>
     */
    private function execute(ToolCall $call, Actor $actor, AiConversation $conversation): array
    {
        $tool = $this->tools->find($call->name, $actor);

        if ($tool === null) {
            return ['error' => 'Cet outil n existe pas ou ne vous est pas accessible.'];
        }

        try {
            return $tool->run($call->arguments, $actor);
        } catch (Throwable $exception) {
            Log::warning('Aeterna Assistant tool failed.', [
                'tool' => $call->name,
                'reason' => class_basename($exception),
            ]);

            return ['error' => 'Cet outil n a pas pu aboutir.'];
        }
    }

    /**
     * The system prompt plus the recent history.
     *
     * @return list<array<string, mixed>>
     */
    private function history(AiConversation $conversation, Actor $actor): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($actor)]];

        $recent = $conversation->messages()
            ->latest('id')
            ->limit((int) config('services.ai.history_limit', 20))
            ->get()
            ->reverse();

        foreach ($recent as $message) {
            $messages[] = $message->role === 'tool'
                ? [
                    'role' => 'tool',
                    'tool_name' => (string) $message->tool_name,
                    'content' => json_encode($message->tool_result ?? [], JSON_UNESCAPED_UNICODE) ?: '{}',
                ]
                : ['role' => $message->role, 'content' => (string) $message->content];
        }

        return $messages;
    }

    /**
     * What the assistant is and what it must not do.
     */
    private function systemPrompt(Actor $actor): string
    {
        $who = $actor->isAlumni()
            ? "Vous parlez a un ancien etudiant de {$actor->organization->name}."
            : "Vous parlez a un membre du personnel de {$actor->organization->name}.";

        return <<<PROMPT
        Vous etes Aeterna Assistant, l assistant de la plateforme alumni.
        {$who} Son nom est {$actor->displayName()}.

        Repondez toujours en francais, de maniere breve et concrete.

        REGLES ABSOLUES :
        1. Pour toute question portant sur des offres d emploi, des evenements,
           des alumni, des candidatures ou des chiffres, vous DEVEZ utiliser un
           outil. Ne repondez jamais de memoire sur ces sujets.
        2. N inventez JAMAIS une offre, un evenement, un alumni, une entreprise,
           une statistique ou un lien. N utilisez que ce qu un outil a renvoye.
        3. Si un outil ne renvoie rien, dites-le simplement : « Je n ai trouve
           aucun resultat. » N essayez pas de combler le vide.
        4. Si un outil renvoie une erreur ou un refus, expliquez-le a l
           utilisateur sans deviner pourquoi.
        5. Avant de rediger une campagne, DEMANDEZ toujours qui doit la
           recevoir : tous les alumni, ou une promotion, un diplome ou une
           entreprise en particulier. Ne choisissez jamais les destinataires a
           la place de l utilisateur.
        6. Si l utilisateur demande de MODIFIER une campagne, modifiez celle qui
           existe avec update_campaign_draft. N en creez jamais une seconde.

        Pour les conseils de carriere, vous pouvez raisonner librement a partir
        du profil, mais presentez-les comme des suggestions et non comme des
        faits.
        PROMPT;
    }

    /**
     * Store the assistant's answer with the tools that built it.
     *
     * @param  list<string>  $used
     */
    private function persist(AiConversation $conversation, string $content, array $used): AiMessage
    {
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content === '' ? 'Je n ai pas de reponse a donner.' : $content,
            'tool_result' => $used === [] ? null : ['tools_used' => array_values(array_unique($used))],
        ]);
    }

    /**
     * How many tool rounds a single question may take.
     */
    private function maxIterations(): int
    {
        return max(1, (int) config('services.ai.max_tool_iterations', 3));
    }
}
