<?php

namespace App\Services\AI\Tools;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Services\AI\Actor;
use App\Services\CampaignRecipientResolver;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\DB;

/**
 * Writing a campaign draft (docs/project.md F14).
 */
class DraftCampaignTool implements Tool
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly PlanLimitService $planLimits,
        private readonly CampaignRecipientResolver $recipients,
    ) {}

    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'draft_campaign';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Redige une campagne e-mail et l enregistre comme BROUILLON, sans rien envoyer. '
            .'DEMANDEZ TOUJOURS a l utilisateur qui doit recevoir la campagne avant d appeler '
            .'cet outil : tous les alumni, ou seulement une promotion, un diplome ou une '
            .'entreprise. Sans le parametre recipients, aucun brouillon n est cree. '
            .'L utilisateur devra ensuite ouvrir la campagne et l envoyer lui-meme.';
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
                'subject' => [
                    'type' => 'string',
                    'description' => 'Objet de l e-mail, court et explicite.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Corps du message, en texte simple.',
                ],
                'recipients' => [
                    'type' => 'object',
                    'description' => 'Qui recoit la campagne. A demander a l utilisateur, jamais a supposer.',
                    'properties' => [
                        'mode' => [
                            'type' => 'string',
                            'enum' => ['all', 'filtered'],
                            'description' => '« all » pour tous les alumni, « filtered » pour une selection.',
                        ],
                        'graduation_year' => ['type' => 'integer', 'description' => 'Promotion exacte.'],
                        'degree' => ['type' => 'string', 'description' => 'Diplome exact.'],
                        'company' => ['type' => 'string', 'description' => 'Entreprise exacte.'],
                        'search' => ['type' => 'string', 'description' => 'Texte libre : nom, e-mail ou entreprise.'],
                    ],
                    'required' => ['mode'],
                ],
            ],
            'required' => ['subject', 'content'],
        ];
    }

    /**
     * Whether this actor may use the tool.
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
        $subject = $this->text($arguments['subject'] ?? null);
        $content = $this->text($arguments['content'] ?? null);

        if ($subject === null || $content === null) {
            return ['error' => 'Un objet et un contenu sont necessaires pour rediger une campagne.'];
        }

        $criteria = $this->criteria($arguments['recipients'] ?? null);

        if ($criteria === null) {
            return $this->askWhoReceivesIt($subject);
        }

        try {
            $this->planLimits->ensureCanCreate($actor->organization, 'campaigns');
        } catch (PlanLimitReachedException $exception) {
            return ['error' => $exception->getMessage()];
        }

        $recipientIds = $this->recipients->ids($criteria);

        if ($recipientIds === []) {
            return [
                'error' => 'Aucun alumni ne correspond a cette selection de destinataires.',
                'audience' => $this->describe($criteria),
                'suggestion' => 'Proposez une autre selection ou l ensemble des alumni.',
            ];
        }

        $campaign = DB::transaction(function () use ($actor, $subject, $content, $recipientIds): Campaign {
            $campaign = Campaign::create([
                'created_by' => $actor->user?->id,
                'subject' => mb_substr($subject, 0, 180),
                'content' => mb_substr($content, 0, 50000),
                'status' => 'draft',
                'recipients_count' => count($recipientIds),
            ]);

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

            return $campaign;
        });

        return [
            'created' => true,
            'campaign_id' => $campaign->id,
            'subject' => $campaign->subject,
            'status' => 'brouillon',
            'audience' => $this->describe($criteria),
            'recipients' => count($recipientIds),
            'sent' => false,
            'next_step' => 'Ouvrez la campagne pour la relire et l envoyer vous-meme. Rien n a ete envoye.',
            'url' => route('campaigns.show', $campaign),
        ];
    }

    /**
     * The question, and the audiences there are to choose from.
     *
     * @return array<string, mixed>
     */
    private function askWhoReceivesIt(string $subject): array
    {
        return [
            'needs_recipients' => true,
            'draft_created' => false,
            'question' => 'Demandez a l utilisateur qui doit recevoir cette campagne, puis rappelez '
                .'draft_campaign avec le parametre recipients. Aucun brouillon n a ete cree.',
            'subject_proposed' => $subject,
            'available_audiences' => [
                'all' => Alumni::query()->count(),
                'graduation_years' => $this->options('graduation_year'),
                'degrees' => $this->options('degree'),
                'companies' => $this->options('company'),
            ],
        ];
    }

    /**
     * The distinct values of one column, with how many alumni each covers.
     *
     * @param  'graduation_year'|'degree'|'company'  $column  the three the campaign screen filters on
     * @return list<array<string, mixed>>
     */
    private function options(string $column): array
    {
        $rows = Alumni::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $options = [];

        foreach ($rows as $row) {
            $options[] = [
                'value' => $row->getAttribute('label'),
                'count' => (int) $row->getAttribute('total'),
            ];
        }

        return $options;
    }

    /**
     * The recipients argument as CampaignRecipientResolver wants it, or null when no audience was named at all.
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
            return ['recipients' => 'all'];
        }

        $criteria = [
            'recipients' => 'filtered',
            'search' => $this->text($recipients['search'] ?? null),
            'degree' => $this->text($recipients['degree'] ?? null),
            'company' => $this->text($recipients['company'] ?? null),
            'graduation_year' => is_numeric($recipients['graduation_year'] ?? null)
                ? (int) $recipients['graduation_year']
                : null,
        ];

        $narrowed = array_filter(
            $criteria,
            fn (mixed $value, string $key): bool => $key !== 'recipients' && $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        return $narrowed === [] ? null : $criteria;
    }

    /**
     * The audience in French, for the answer the user reads.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function describe(array $criteria): string
    {
        if (($criteria['recipients'] ?? 'all') === 'all') {
            return 'Tous les alumni';
        }

        $parts = [];

        foreach ([
            'graduation_year' => 'promotion',
            'degree' => 'diplome',
            'company' => 'entreprise',
            'search' => 'recherche',
        ] as $key => $label) {
            if (($criteria[$key] ?? null) !== null) {
                $parts[] = "{$label} : {$criteria[$key]}";
            }
        }

        return $parts === [] ? 'Tous les alumni' : implode(', ', $parts);
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
