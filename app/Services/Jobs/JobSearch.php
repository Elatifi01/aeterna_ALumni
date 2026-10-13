<?php

namespace App\Services\Jobs;

use App\Services\EmploiMa\JobQuery;

/**
 * One search across every job source the platform offers (docs/project.md F11).
 */
class JobSearch
{
    /**
     * Create a new instance.
     *
     * @param  list<JobSource>  $sources
     */
    public function __construct(private readonly array $sources) {}

    /**
     * The sources that are switched on and able to answer, keyed by their key.
     *
     * @return array<string, JobSource>
     */
    public function available(): array
    {
        $available = [];

        foreach ($this->sources as $source) {
            if ($source->isConfigured()) {
                $available[$source->key()] = $source;
            }
        }

        return $available;
    }

    /**
     * Search the requested sources and merge their offers, each tagged with where it came from.
     *
     * @param  list<string>  $keys  the sources to ask; every available one when empty
     * @return array{offers: list<array<string, mixed>>, total: int, sources: array<string, array{label: string, degraded: bool, reason: string|null, returned: int}>}
     */
    public function search(JobQuery $query, array $keys = []): array
    {
        $available = $this->available();
        $asked = $keys === [] ? array_keys($available) : array_values(array_intersect($keys, array_keys($available)));

        $offers = [];
        $total = 0;
        $statuses = [];

        foreach ($asked as $key) {
            $result = $available[$key]->search($query);

            $offers = [...$offers, ...$result['offers']];
            $total += $result['total'];
            $statuses[$key] = [
                'label' => $available[$key]->label(),
                'degraded' => $result['degraded'],
                'reason' => $result['reason'],
                'returned' => count($result['offers']),
            ];
        }

        return [
            'offers' => $this->interleave($offers, $asked),
            'total' => $total,
            'sources' => $statuses,
        ];
    }

    /**
     * Take offers from each source in turn, so one board does not push the others off the first screen.
     *
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function interleave(array $offers, array $keys): array
    {
        if (count($keys) < 2) {
            return $offers;
        }

        $queues = array_fill_keys($keys, []);

        foreach ($offers as $offer) {
            $queues[$offer['source']][] = $offer;
        }

        $merged = [];

        while (array_filter($queues) !== []) {
            foreach ($queues as $key => $queue) {
                if ($queue !== []) {
                    $merged[] = array_shift($queues[$key]);
                }
            }
        }

        return $merged;
    }
}
