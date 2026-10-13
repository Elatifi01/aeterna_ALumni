<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Actor;
use App\Services\EmploiMa\JobQuery;
use App\Services\Jobs\JobSearch;

/**
 * Job offers, for the assistant (docs/project.md F11/F14).
 */
class SearchJobsTool implements Tool
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly JobSearch $search) {}

    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'search_jobs';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Recherche des offres d emploi reelles sur les sites d emploi proposes par la '
            .'plateforme (emploi.ma, et selon la configuration Indeed et LinkedIn). A utiliser des '
            .'que l utilisateur demande des offres, des postes ou du travail. La ville filtre les '
            .'resultats obtenus. Chaque offre indique sa source.';
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
                'keywords' => [
                    'type' => 'string',
                    'description' => 'Metier ou competence recherche, par exemple "developpeur" ou "comptable".',
                ],
                'city' => [
                    'type' => 'string',
                    'description' => 'Ville marocaine, par exemple "Casablanca". Facultatif.',
                ],
            ],
            'required' => ['keywords'],
        ];
    }

    /**
     * Whether this actor may use the tool.
     */
    public function authorize(Actor $actor): bool
    {
        return $actor->isAlumni() ? $actor->can('portal.jobs.view') : true;
    }

    /**
     * Run the tool with the arguments the model supplied.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments, Actor $actor): array
    {
        $query = JobQuery::fromInput([
            'keywords' => is_scalar($arguments['keywords'] ?? null) ? (string) $arguments['keywords'] : '',
            'city' => is_scalar($arguments['city'] ?? null) ? (string) $arguments['city'] : '',
        ]);

        if ($query->isEmpty()) {
            return ['error' => 'Precisez un metier ou une competence a rechercher.'];
        }

        $results = $this->search->search($query);

        $answered = array_filter($results['sources'], fn (array $status): bool => ! $status['degraded']);

        if ($results['sources'] === [] || $answered === []) {
            return [
                'unavailable' => true,
                'message' => 'La recherche d offres est momentanement indisponible.',
                'offers' => [],
            ];
        }

        $unavailable = array_map(
            fn (array $status): string => $status['label'],
            array_filter($results['sources'], fn (array $status): bool => $status['degraded']),
        );

        return [
            'count' => count($results['offers']),
            'returned_before_city_filter' => $results['total'],
            'sources_searched' => array_values(array_map(fn (array $status): string => $status['label'], $answered)),
            'sources_unavailable' => array_values($unavailable),
            'offers' => array_map(fn (array $offer): array => [
                'source' => $offer['source_label'],
                'title' => $offer['title'],
                'company' => $offer['company'],
                'location' => $offer['location'],
                'employment_type' => $offer['employment_type'],
                'experience_level' => $offer['experience_level'],
                'summary' => $offer['snippet'],
                'link' => $offer['link'],
            ], array_slice($results['offers'], 0, 8)),
        ];
    }
}
