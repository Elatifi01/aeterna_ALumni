<?php

namespace App\Services\Jobs;

use App\Services\EmploiMa\CreditBudget;
use App\Services\EmploiMa\JobOffer;
use App\Services\EmploiMa\JobQuery;
use App\Services\EmploiMa\RateLimited;
use App\Services\EmploiMa\RequestFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A job source scraped by Parse.bot, described entirely by configuration (docs/project.md F11).
 */
class ParseBotSource implements JobSource
{
    private const STALE_MULTIPLIER = 30;

    /**
     * Create a new instance.
     *
     * @param  array<string, mixed>  $config  one entry of services.jobs.sources
     */
    public function __construct(
        private readonly string $key,
        private readonly array $config,
        private readonly CreditBudget $budget,
    ) {}

    /**
     * The short key the interface and the URL use.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * The name shown to people.
     */
    public function label(): string
    {
        return (string) ($this->config['label'] ?? $this->key);
    }

    /**
     * Whether the source is enabled and has a URL, an endpoint and an API key.
     */
    public function isConfigured(): bool
    {
        $apiKey = config('services.parsebot.key');

        return (bool) ($this->config['enabled'] ?? false)
            && ($this->config['base_url'] ?? '') !== ''
            && ($this->config['endpoint'] ?? '') !== ''
            && is_string($apiKey)
            && $apiKey !== '';
    }

    /**
     * Search this source, serving from cache and spending a credit only on a miss.
     *
     * @return array{offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool}
     */
    public function search(JobQuery $query): array
    {
        $cacheKey = "jobs:{$this->key}:search:".md5(json_encode($this->parameters($query), JSON_THROW_ON_ERROR));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $this->result($cached, $query, degraded: false, reason: null, cached: true);
        }

        if (! $this->isConfigured()) {
            return $this->degrade($cacheKey, $query, 'not-configured');
        }

        if (! $this->budget->trySpend()) {
            return $this->degrade($cacheKey, $query, 'budget-exhausted');
        }

        try {
            $rows = $this->request($query);
        } catch (RateLimited) {
            return $this->degrade($cacheKey, $query, 'rate-limited');
        } catch (Throwable $exception) {
            Log::warning("{$this->label()} search failed.", ['reason' => class_basename($exception)]);

            return $this->degrade($cacheKey, $query, 'upstream-error');
        }

        $payload = ['jobs' => $rows];

        Cache::put($cacheKey, $payload, $this->freshFor());
        Cache::put($cacheKey.':stale', $payload, $this->freshFor() * self::STALE_MULTIPLIER);

        return $this->result($payload, $query, degraded: false, reason: null, cached: false);
    }

    /**
     * Call the scraper and return the raw offer rows.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RateLimited|RequestFailed
     */
    private function request(JobQuery $query): array
    {
        $base = rtrim((string) $this->config['base_url'], '/');
        $endpoint = ltrim((string) $this->config['endpoint'], '/');

        $response = Http::withHeaders(['X-API-Key' => (string) config('services.parsebot.key')])
            ->acceptJson()
            ->timeout(20)
            ->get("{$base}/{$endpoint}", $this->parameters($query));

        $this->budget->readFrom($response);

        if ($response->status() === 429) {
            throw new RateLimited;
        }

        if (! $response->successful()) {
            throw new RequestFailed("{$this->label()} responded {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body) || ($body['status'] ?? null) !== 'success') {
            throw new RequestFailed("{$this->label()} returned an unexpected body.");
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $rows = data_get($data, (string) ($this->config['jobs_key'] ?? 'jobs'));

        $list = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $list[] = $row;
            }
        }

        return $list;
    }

    /**
     * The query string this scraper expects: defaults, then the mapped query values, then fixed values.
     *
     * @return array<string, string>
     */
    private function parameters(JobQuery $query): array
    {
        $map = is_array($this->config['parameters'] ?? null) ? $this->config['parameters'] : [];
        $values = [
            'keywords' => $query->keywords,
            'city' => $query->city,
            'page' => (string) $query->page,
            'page_zero' => (string) ($query->page - 1),
        ];

        $parameters = [];

        foreach (is_array($this->config['default_parameters'] ?? null) ? $this->config['default_parameters'] : [] as $name => $value) {
            $parameters[(string) $name] = (string) $value;
        }

        foreach ($map as $ours => $theirs) {
            $value = $values[$ours] ?? '';

            if (is_string($theirs) && $value !== '') {
                $parameters[$theirs] = $value;
            }
        }

        foreach (is_array($this->config['fixed_parameters'] ?? null) ? $this->config['fixed_parameters'] : [] as $name => $value) {
            $parameters[(string) $name] = (string) $value;
        }

        return $parameters;
    }

    /**
     * Fall back to the last good page for this search, or to nothing.
     *
     * @return array{offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool}
     */
    private function degrade(string $cacheKey, JobQuery $query, string $reason): array
    {
        $stale = Cache::get($cacheKey.':stale');

        if (is_array($stale)) {
            return $this->result($stale, $query, degraded: true, reason: $reason, cached: true);
        }

        return ['offers' => [], 'total' => 0, 'degraded' => true, 'reason' => $reason, 'cached' => false];
    }

    /**
     * Build a search result from cached rows, mapping each through the field map.
     *
     * @param  array<string, mixed>  $payload
     * @return array{offers: list<array<string, mixed>>, total: int, degraded: bool, reason: string|null, cached: bool}
     */
    private function result(array $payload, JobQuery $query, bool $degraded, ?string $reason, bool $cached): array
    {
        $offers = [];

        foreach (is_array($payload['jobs'] ?? null) ? $payload['jobs'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $offer = JobOffer::fromApi($this->translate($row));

            if ($offer !== null) {
                $offers[] = [...$offer->toArray(), 'source' => $this->key, 'source_label' => $this->label()];
            }
        }

        $returned = count($offers);

        return [
            'offers' => JobOffer::narrowToCity($offers, $query->city),
            'total' => $returned,
            'degraded' => $degraded,
            'reason' => $reason,
            'cached' => $cached,
        ];
    }

    /**
     * Rename the scraper's fields to the ones JobOffer reads.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function translate(array $row): array
    {
        $fields = is_array($this->config['fields'] ?? null) ? $this->config['fields'] : [];

        if ($fields === []) {
            return $row;
        }

        $translated = [];

        foreach ($fields as $ours => $theirs) {
            $translated[$ours] = is_string($theirs) ? data_get($row, $theirs) : null;
        }

        return $translated;
    }

    /**
     * How long a fresh result is served before the scraper is asked again.
     */
    private function freshFor(): int
    {
        return max(60, (int) config('services.parsebot.cache_ttl', 86400));
    }
}
