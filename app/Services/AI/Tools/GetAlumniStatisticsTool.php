<?php

namespace App\Services\AI\Tools;

use App\Models\Alumni;
use App\Services\AI\Actor;

/**
 * Counts over the establishment's alumni (docs/project.md F14).
 */
class GetAlumniStatisticsTool implements Tool
{
    private const DIMENSIONS = [
        'city' => 'ville',
        'company' => 'entreprise',
        'degree' => 'diplome',
        'graduation_year' => 'promotion',
    ];

    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'get_alumni_statistics';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Renvoie des statistiques reelles sur les alumni de l etablissement : '
            .'effectif total et repartition par ville, entreprise, diplome ou promotion. '
            .'A utiliser pour toute question comportant « combien », « repartition » ou « les plus ».';
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
                'group_by' => [
                    'type' => 'string',
                    'enum' => array_keys(self::DIMENSIONS),
                    'description' => 'Dimension de repartition. Omettre pour le seul effectif total.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Restreint le calcul aux alumni correspondant a ce texte.',
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
        return $actor->can('alumni.view');
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

        $base = fn () => Alumni::query()->filtered(['search' => $search === '' ? null : $search]);

        $result = [
            'total' => $base()->count(),
            'organization' => $actor->organization->name,
        ];

        $groupBy = is_string($arguments['group_by'] ?? null) ? $arguments['group_by'] : null;

        if ($groupBy === null || ! isset(self::DIMENSIONS[$groupBy])) {
            return $result;
        }

        $rows = $base()
            ->whereNotNull($groupBy)
            ->where($groupBy, '!=', '')
            ->selectRaw("{$groupBy} as label, COUNT(*) as total")
            ->groupBy($groupBy)
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[] = [
                'label' => (string) $row->getAttribute('label'),
                'count' => (int) $row->getAttribute('total'),
            ];
        }

        $result['grouped_by'] = self::DIMENSIONS[$groupBy];
        $result['breakdown'] = $breakdown;

        return $result;
    }
}
