<?php

namespace App\Services\EmploiMa;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * How much of the emploi.ma allowance is left (docs/project.md F11).
 */
class CreditBudget
{
    private const SPENT_PREFIX = 'emploima:spent:';

    private const REPORTED_KEY = 'emploima:reported';

    /**
     * Reserve one credit, or refuse.
     */
    public function trySpend(): bool
    {
        $budget = $this->monthlyBudget();

        if ($budget <= 0) {
            return false;
        }

        $key = $this->monthKey();

        Cache::add($key, 0, now()->endOfMonth());

        $spent = (int) Cache::increment($key);

        if ($spent > $budget) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    /**
     * Record what Parse.bot said about the allowance on its last answer.
     */
    public function readFrom(Response $response): void
    {
        $remaining = $response->header('x-credits-remaining');
        $limit = $response->header('x-credits-limit');

        if ($remaining === '' || ! is_numeric($remaining)) {
            return;
        }

        Cache::forever(self::REPORTED_KEY, [
            'remaining' => (int) $remaining,
            'limit' => is_numeric($limit) ? (int) $limit : null,
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Credits this installation has spent this month.
     */
    public function spentThisMonth(): int
    {
        return (int) Cache::get($this->monthKey(), 0);
    }

    /**
     * The number of API credits allowed per month.
     */
    public function monthlyBudget(): int
    {
        return (int) config('services.parsebot.monthly_budget', 0);
    }

    /**
     * What the platform dashboard shows, so the allowance is visible before it runs out rather than after.
     *
     * @return array{spent: int, budget: int, remaining: int|null, limit: int|null, reported_at: string|null}
     */
    public function summary(): array
    {
        /** @var array<string, mixed>|null $reported */
        $reported = Cache::get(self::REPORTED_KEY);

        return [
            'spent' => $this->spentThisMonth(),
            'budget' => $this->monthlyBudget(),
            'remaining' => is_array($reported) && is_int($reported['remaining'] ?? null)
                ? $reported['remaining']
                : null,
            'limit' => is_array($reported) && is_int($reported['limit'] ?? null)
                ? $reported['limit']
                : null,
            'reported_at' => is_array($reported) && is_string($reported['at'] ?? null)
                ? $reported['at']
                : null,
        ];
    }

    /**
     * The cache key for the current month's counter.
     */
    private function monthKey(): string
    {
        return self::SPENT_PREFIX.now()->format('Y-m');
    }
}
