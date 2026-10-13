<?php

namespace App\Services;

use App\Models\Alumni;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a campaign's recipient criteria into alumni (docs/project.md F5: "select recipients among the organization's alumni, using the same filters as F3").
 */
class CampaignRecipientResolver
{
    /**
     * The alumni query matching the recipient criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return Builder<Alumni>
     */
    public function query(array $criteria): Builder
    {
        $query = Alumni::query();

        if (($criteria['recipients'] ?? 'all') === 'all') {
            return $query;
        }

        return $query
            ->search($criteria['search'] ?? null)
            ->when($criteria['graduation_year'] ?? null, fn ($q, $year) => $q->where('graduation_year', $year))
            ->when($criteria['degree'] ?? null, fn ($q, $degree) => $q->where('degree', $degree))
            ->when($criteria['company'] ?? null, fn ($q, $company) => $q->where('company', $company));
    }

    /**
     * The ids of the alumni matching the criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int, int>
     */
    public function ids(array $criteria): array
    {
        return $this->query($criteria)->pluck('id')->all();
    }

    /**
     * How many alumni match the criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function count(array $criteria): int
    {
        return $this->query($criteria)->count();
    }
}
