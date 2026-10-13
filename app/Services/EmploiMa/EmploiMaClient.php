<?php

namespace App\Services\EmploiMa;

use App\Services\Jobs\JobSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The emploi.ma job search, through Parse.bot (docs/project.md F11).
 */
class EmploiMaClient implements JobSource
{
    private const SEARCH_CACHE = 'emploima:search:';

    private const CATEGORY_CACHE = 'emploima:categories';

    private const STALE_MULTIPLIER = 30;

    /**
     * Create a new instance.
     */
    public function __construct(private readonly CreditBudget $budget) {}

    /**
     * The short key the interface and the URL use.
     */
    public function key(): string
    {
        return 'emploima';
    }

    /**
     * The name shown to people.
     */
    public function label(): string
    {
        return 'emploi.ma';
    }

    /**
     * Search job offers, serving from cache and spending a credit only on a miss.
     *
     * @return array{ offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool }
     */
    public function search(JobQuery $query): array
    {
        $key = self::SEARCH_CACHE.$query->fingerprint();

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $this->result($cached, $query, degraded: false, reason: null, cached: true);
        }

        if (! $this->isConfigured()) {
            return $this->degrade($key, $query, 'not-configured');
        }

        if (! $this->budget->trySpend()) {
            return $this->degrade($key, $query, 'budget-exhausted');
        }

        try {
            $jobs = $this->call('search_jobs', $query->toParameters());
        } catch (RateLimited) {
            return $this->degrade($key, $query, 'rate-limited');
        } catch (Throwable $exception) {
            Log::warning('emploi.ma search failed.', ['reason' => class_basename($exception)]);

            return $this->degrade($key, $query, 'upstream-error');
        }

        $payload = ['jobs' => $jobs['jobs'], 'count' => $jobs['count']];

        Cache::put($key, $payload, $this->freshFor());
        Cache::put($key.':stale', $payload, $this->freshFor() * self::STALE_MULTIPLIER);

        return $this->result($payload, $query, degraded: false, reason: null, cached: false);
    }

    /**
     * The job categories, for the Metier dropdown.
     *
     * @return list<array{id: string, name: string, count: int|null}>
     */
    public function categories(): array
    {
        $cached = Cache::get(self::CATEGORY_CACHE);

        if (is_array($cached)) {
            return $this->normaliseCategories($cached);
        }

        if (! $this->isConfigured() || ! $this->budget->trySpend()) {
            return [];
        }

        try {
            $data = $this->request('list_job_categories', []);
        } catch (Throwable $exception) {
            Log::warning('emploi.ma categories failed.', ['reason' => class_basename($exception)]);

            return [];
        }

        $categories = $this->normaliseCategories(
            is_array($data['categories'] ?? null) ? $data['categories'] : [],
        );

        Cache::put(self::CATEGORY_CACHE, $categories, now()->addWeek());

        return $categories;
    }

    /**
     * The category filters trimmed, lower-cased and sorted.
     *
     * @param  array<mixed>  $rows
     * @return list<array{id: string, name: string, count: int|null}>
     */
    private function normaliseCategories(array $rows): array
    {
        $categories = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id']) && is_scalar($row['id']) ? (string) $row['id'] : '';
            $name = isset($row['name']) && is_string($row['name']) ? trim($row['name']) : '';

            if ($id === '' || $name === '') {
                continue;
            }

            $categories[] = [
                'id' => $id,
                'name' => $name,
                'count' => isset($row['count']) && is_numeric($row['count']) ? (int) $row['count'] : null,
            ];
        }

        return $categories;
    }

    /**
     * Whether the board can work at all.
     */
    public function isConfigured(): bool
    {
        $key = config('services.parsebot.key');

        return (bool) config('services.emploima.enabled')
            && is_string($key)
            && $key !== '';
    }

    /**
     * Perform the HTTP request to the API.
     *
     * @param  array<string, string>  $parameters
     * @return array{jobs: list<array<string, mixed>>, count: int}
     */
    private function call(string $endpoint, array $parameters): array
    {
        $data = $this->request($endpoint, $parameters);

        $jobs = is_array($data['jobs'] ?? null) ? array_values($data['jobs']) : [];

        return [
            'jobs' => $jobs,
            'count' => is_numeric($data['count'] ?? null) ? (int) $data['count'] : count($jobs),
        ];
    }

    /**
     * One call, unwrapped.
     *
     * @param  array<string, string>  $parameters
     * @return array<string, mixed>
     *
     * @throws RateLimited|RequestFailed
     */
    private function request(string $endpoint, array $parameters): array
    {
        $base = rtrim((string) config('services.emploima.base_url'), '/');

        $response = Http::withHeaders(['X-API-Key' => (string) config('services.parsebot.key')])
            ->acceptJson()
            ->timeout(20)
            ->get("{$base}/{$endpoint}", $parameters);

        $this->budget->readFrom($response);

        if ($response->status() === 429) {
            throw new RateLimited;
        }

        if (! $response->successful()) {
            throw new RequestFailed("emploi.ma responded {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body) || ($body['status'] ?? null) !== 'success') {
            throw new RequestFailed('emploi.ma returned an unexpected body.');
        }

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    /**
     * Fall back to the last good page for this search, or to nothing.
     *
     * @return array{offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool}
     */
    private function degrade(string $key, JobQuery $query, string $reason): array
    {
        $stale = Cache::get($key.':stale');

        if (is_array($stale)) {
            return $this->result($stale, $query, degraded: true, reason: $reason, cached: true);
        }

        return [
            'offers' => [],
            'total' => 0,
            'degraded' => true,
            'reason' => $reason,
            'cached' => false,
        ];
    }

    /**
     * Build a search result from the API response.
     *
     * @param  array<string, mixed>  $payload
     * @return array{offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool}
     */
    private function result(array $payload, JobQuery $query, bool $degraded, ?string $reason, bool $cached): array
    {
        $rows = is_array($payload['jobs'] ?? null) ? $payload['jobs'] : [];

        $offers = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $offer = JobOffer::fromApi($row);

            if ($offer !== null) {
                $offers[] = [...$offer->toArray(), 'source' => $this->key(), 'source_label' => $this->label()];
            }
        }

        $returned = count($offers);

        $offers = JobOffer::narrowToCity($offers, $query->city);

        return [
            'offers' => $offers,
            'total' => $returned,
            'degraded' => $degraded,
            'reason' => $reason,
            'cached' => $cached,
        ];
    }

    /**
     * Fetch and cache the result for a query.
     */
    private function freshFor(): int
    {
        return max(60, (int) config('services.parsebot.cache_ttl', 86400));
    }
}
