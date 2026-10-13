<?php

namespace App\Services\AI\Tools;

use App\Models\Campaign;
use App\Services\AI\Actor;

/**
 * The establishment's campaigns, so one can be named (docs/project.md F14).
 */
class ListCampaignsTool implements Tool
{
    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'list_campaigns';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Liste les campagnes de l etablissement, les plus recentes d abord. '
            .'A utiliser pour retrouver une campagne dont l utilisateur parle, avant de '
            .'la modifier. Seules les campagnes au statut brouillon peuvent etre modifiees.';
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
                'search' => [
                    'type' => 'string',
                    'description' => 'Filtre sur l objet de la campagne.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'sending', 'sent', 'failed'],
                    'description' => 'Ne renvoyer que les campagnes de ce statut.',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Whether this actor may use the tool.
     */
    public function authorize(Actor $actor): bool
    {
        return $actor->can('campaigns.view');
    }

    /**
     * Run the tool with the arguments the model supplied.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments, Actor $actor): array
    {
        $search = is_scalar($arguments['search'] ?? null) ? trim((string) $arguments['search']) : '';
        $status = is_string($arguments['status'] ?? null) ? $arguments['status'] : null;

        $query = Campaign::query()
            ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.addcslashes($search, '%_').'%'))
            ->when(
                in_array($status, ['draft', 'sending', 'sent', 'failed'], true),
                fn ($q) => $q->where('status', $status),
            );

        $rows = [];

        foreach ($query->latest('created_at')->limit(20)->get() as $campaign) {
            $rows[] = [
                'campaign_id' => $campaign->id,
                'subject' => $campaign->subject,
                'status' => $campaign->status,
                'recipients' => $campaign->recipients_count,
                'editable' => $campaign->status === 'draft',
                'created_at' => $campaign->created_at?->toDateString(),
            ];
        }

        return [
            'total' => count($rows),
            'campaigns' => $rows,
        ];
    }
}
