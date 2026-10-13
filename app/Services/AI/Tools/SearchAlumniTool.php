<?php

namespace App\Services\AI\Tools;

use App\Models\Alumni;
use App\Services\AI\Actor;

/**
 * Finding alumni, for staff (docs/project.md F14).
 */
class SearchAlumniTool implements Tool
{
    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'search_alumni';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Recherche des alumni de l etablissement. La recherche libre porte sur '
            .'le nom, l e-mail et l entreprise. Les competences ne sont pas enregistrees '
            .'dans Aeterna : utilisez le poste, le diplome ou l entreprise a la place.';
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
                    'description' => 'Texte libre : nom, e-mail ou entreprise.',
                ],
                'company' => ['type' => 'string', 'description' => 'Entreprise exacte.'],
                'degree' => ['type' => 'string', 'description' => 'Diplome exact.'],
                'graduation_year' => ['type' => 'integer', 'description' => 'Annee de promotion.'],
                'city' => ['type' => 'string', 'description' => 'Ville de residence.'],
            ],
            'required' => [],
        ];
    }

    /**
     * The same permission the list screen requires.
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
        $query = Alumni::query()->filtered([
            'search' => $this->text($arguments['search'] ?? null),
            'company' => $this->text($arguments['company'] ?? null),
            'degree' => $this->text($arguments['degree'] ?? null),
            'graduation_year' => is_numeric($arguments['graduation_year'] ?? null)
                ? (int) $arguments['graduation_year']
                : null,
        ]);

        $city = $this->text($arguments['city'] ?? null);

        if ($city !== null) {
            $query->where('city', 'like', '%'.addcslashes($city, '%_').'%');
        }

        $total = (clone $query)->count();

        $alumni = $query->orderBy('last_name')->limit(25)->get();

        return [
            'total_matching' => $total,
            'showing' => $alumni->count(),
            'alumni' => $alumni->map(fn (Alumni $one): array => [
                'name' => $one->full_name,
                'email' => $one->email,
                'degree' => $one->degree,
                'graduation_year' => $one->graduation_year,
                'job_title' => $one->job_title,
                'company' => $one->company,
                'city' => $one->city,
            ])->all(),
        ];
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
