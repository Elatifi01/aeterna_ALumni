<?php

namespace App\Services\AI\Tools;

use App\Models\Campaign;
use App\Services\AI\Actor;
use App\Services\CampaignRecipientResolver;
use Illuminate\Support\Facades\DB;

/**
 * Changing a campaign that is still a draft (docs/project.md F14).
 */
class UpdateCampaignDraftTool implements Tool
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly CampaignRecipientResolver $recipients) {}

    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'update_campaign_draft';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Modifie une campagne EXISTANTE encore au statut brouillon : son objet, '
            .'son contenu ou ses destinataires. A utiliser des que l utilisateur demande de '
            .'modifier, corriger ou completer une campagne — ne creez jamais une nouvelle '
            .'campagne pour cela. Utilisez « add » pour ajouter des destinataires aux '
            .'existants, « all » ou « filtered » pour remplacer entierement la selection.';
    }

    /**
     * The JSON schema of the tool's arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'campaign_id' => [
                    'type' => 'integer',
                    'description' => 'Identifiant de la campagne a modifier, obtenu via list_campaigns.',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Nouvel objet. Omettre pour le laisser inchange.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Nouveau corps du message. Omettre pour le laisser inchange.',
                ],
                'recipients' => [
                    'type' => 'object',
                    'description' => 'Nouveaux destinataires. Omettre pour les laisser inchanges.',
                    'properties' => [
                        'mode' => [
                            'type' => 'string',
                            'enum' => ['all', 'filtered', 'add'],
                            'description' => '« all » tous les alumni, « filtered » remplace la selection, '
                                .'« add » ajoute aux destinataires deja presents.',
                        ],
                        'graduation_year' => ['type' => 'integer', 'description' => 'Promotion exacte.'],
                        'degree' => ['type' => 'string', 'description' => 'Diplome exact.'],
                        'company' => ['type' => 'string', 'description' => 'Entreprise exacte.'],
                        'search' => ['type' => 'string', 'description' => 'Texte libre : nom, e-mail ou entreprise.'],
                    ],
                    'required' => ['mode'],
                ],
            ],
            'required' => ['campaign_id'],
        ];
    }

    /**
     * The permission the edit screen requires.
     */
    public function authorize(Actor $actor): bool
    {
        return $actor->can('campaigns.create');
    }

    /**
     * Run the tool with the arguments the model supplied.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments, Actor $actor): array
    {
        if (! is_numeric($arguments['campaign_id'] ?? null)) {
            return ['error' => 'Precisez la campagne a modifier. Utilisez list_campaigns pour la retrouver.'];
        }

        $campaign = Campaign::query()->find((int) $arguments['campaign_id']);

        if ($campaign === null) {
            return ['error' => 'Cette campagne n existe pas.'];
        }

        if ($campaign->status !== 'draft') {
            return [
                'error' => 'Cette campagne n est plus un brouillon, elle ne peut plus etre modifiee.',
                'status' => $campaign->status,
                'suggestion' => 'Proposez de creer une nouvelle campagne si l utilisateur le souhaite.',
            ];
        }

        $subject = $this->text($arguments['subject'] ?? null);
        $content = $this->text($arguments['content'] ?? null);
        $criteria = $this->criteria($arguments['recipients'] ?? null);

        if ($subject === null && $content === null && $criteria === null) {
            return ['error' => 'Indiquez ce qui doit changer : l objet, le contenu ou les destinataires.'];
        }

        $recipientIds = $criteria === null ? null : $this->resolve($campaign, $criteria);

        if ($recipientIds === []) {
            return [
                'error' => 'Aucun alumni ne correspond a cette selection de destinataires.',
                'suggestion' => 'Proposez une autre selection ou l ensemble des alumni.',
            ];
        }

        DB::transaction(function () use ($campaign, $subject, $content, $recipientIds): void {
            $campaign->update(array_filter([
                'subject' => $subject === null ? null : mb_substr($subject, 0, 180),
                'content' => $content === null ? null : mb_substr($content, 0, 50000),
                'recipients_count' => $recipientIds === null ? null : count($recipientIds),
            ], fn (mixed $value): bool => $value !== null));

            if ($recipientIds === null) {
                return;
            }

            $now = now();

            $campaign->recipients()->sync(
                collect($recipientIds)
                    ->mapWithKeys(fn (int $id) => [$id => [
                        'status' => 'pending',
                        'sent_at' => null,
                        'error_message' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]])
                    ->all(),
            );
        });

        $campaign->refresh();

        return [
            'updated' => true,
            'campaign_id' => $campaign->id,
            'subject' => $campaign->subject,
            'status' => 'brouillon',
            'recipients' => $campaign->recipients_count,
            'changed' => array_values(array_filter([
                $subject === null ? null : 'objet',
                $content === null ? null : 'contenu',
                $recipientIds === null ? null : 'destinataires',
            ])),
            'sent' => false,
            'next_step' => 'Ouvrez la campagne pour la relire et l envoyer vous-meme. Rien n a ete envoye.',
            'url' => route('campaigns.show', $campaign),
        ];
    }

    /**
     * The recipient ids this change leaves the campaign with.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int, int>
     */
    private function resolve(Campaign $campaign, array $criteria): array
    {
        $resolved = $this->recipients->ids($criteria);

        if (($criteria['mode'] ?? null) !== 'add') {
            return $resolved;
        }

        $existing = $campaign->recipients()->pluck('alumni.id')->all();

        return array_values(array_unique([...$existing, ...$resolved]));
    }

    /**
     * The recipients argument as the resolver wants it, or null when unchanged.
     *
     * @return array<string, mixed>|null
     */
    private function criteria(mixed $recipients): ?array
    {
        if (! is_array($recipients)) {
            return null;
        }

        $mode = is_string($recipients['mode'] ?? null) ? strtolower($recipients['mode']) : null;

        if ($mode === 'all') {
            return ['recipients' => 'all', 'mode' => 'all'];
        }

        if (! in_array($mode, ['filtered', 'add'], true)) {
            return null;
        }

        $criteria = [
            'recipients' => 'filtered',
            'mode' => $mode,
            'search' => $this->text($recipients['search'] ?? null),
            'degree' => $this->text($recipients['degree'] ?? null),
            'company' => $this->text($recipients['company'] ?? null),
            'graduation_year' => is_numeric($recipients['graduation_year'] ?? null)
                ? (int) $recipients['graduation_year']
                : null,
        ];

        $narrowed = array_filter(
            $criteria,
            fn (mixed $value, string $key): bool => ! in_array($key, ['recipients', 'mode'], true)
                && $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        return $narrowed === [] ? null : $criteria;
    }

    /**
     * A trimmed string from a scalar argument, or null.
     */
    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
