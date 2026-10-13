<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Actor;

/**
 * The alumni's own record (docs/project.md F14).
 */
class GetMyProfileTool implements Tool
{
    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'get_my_profile';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Renvoie le profil de l utilisateur connecte : diplome, promotion, '
            .'poste actuel, entreprise et ville. A utiliser avant de conseiller ou '
            .'de comparer des offres a son profil.';
    }

    /**
     * The JSON schema of the tool's arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }

    /**
     * Only an alumni has one of these.
     */
    public function authorize(Actor $actor): bool
    {
        return $actor->isAlumni();
    }

    /**
     * Run the tool with the arguments the model supplied.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments, Actor $actor): array
    {
        $alumni = $actor->alumni;

        if ($alumni === null) {
            return ['error' => 'Aucun profil disponible.'];
        }

        return [
            'first_name' => $alumni->first_name,
            'last_name' => $alumni->last_name,
            'degree' => $alumni->degree,
            'graduation_year' => $alumni->graduation_year,
            'job_title' => $alumni->job_title,
            'company' => $alumni->company,
            'city' => $alumni->city,
            'organization' => $actor->organization->name,
        ];
    }
}
