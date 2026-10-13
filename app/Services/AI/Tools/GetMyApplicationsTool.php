<?php

namespace App\Services\AI\Tools;

use App\Models\JobApplication;
use App\Services\AI\Actor;

/**
 * What this alumni saved or applied to (docs/project.md F11/F14).
 */
class GetMyApplicationsTool implements Tool
{
    /**
     * The tool's name as the model calls it.
     */
    public function name(): string
    {
        return 'get_my_applications';
    }

    /**
     * What the tool does, as told to the model.
     */
    public function description(): string
    {
        return 'Liste les offres que l utilisateur a enregistrees ou auxquelles il a postule. '
            .'A utiliser pour toute question sur ses candidatures.';
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
     * Whether this actor may use the tool.
     */
    public function authorize(Actor $actor): bool
    {
        return $actor->isAlumni() && $actor->can('portal.jobs.view');
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
            return ['error' => 'Aucune candidature disponible.'];
        }

        $applications = JobApplication::query()
            ->where('alumni_id', $alumni->id)
            ->latest()
            ->limit(25)
            ->get();

        return [
            'count' => $applications->count(),
            'applications' => $applications->map(fn (JobApplication $one): array => [
                'title' => $one->title,
                'company' => $one->company,
                'location' => $one->location,
                'status' => $one->status === 'applied' ? 'candidature envoyee' : 'enregistree',
                'applied_at' => $one->applied_at?->toDateString(),
                'link' => $one->link,
            ])->all(),
        ];
    }
}
