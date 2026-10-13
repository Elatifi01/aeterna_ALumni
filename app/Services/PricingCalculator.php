<?php

namespace App\Services;

/**
 * Works out what a bespoke subscription costs from the limits chosen for it (docs/project.md F6).
 */
class PricingCalculator
{
    public const RESOURCES = ['alumni', 'events', 'campaigns', 'users'];

    /** @var list<string> */
    public const FEATURES = ['alumni_portal'];

    /**
     * Compute the price of a bespoke plan from its limits and features.
     *
     * @param  array<string, int|null>  $limits  resource => limit, null meaning unlimited
     * @param  array<string, bool>  $features  capability => switched on
     * @return array{ monthly: float, yearly: float, base: float, lines: list<array{resource: string, limit: int|null, blocks: int|null, amount: float}>, features: list<array{feature: string, label: string, enabled: bool, amount: float}> }
     */
    public function quote(array $limits, array $features = []): array
    {
        $base = (float) config('pricing.base_monthly', 0);
        $monthly = $base;
        $lines = [];

        foreach (self::RESOURCES as $resource) {
            $limit = $limits[$resource] ?? null;
            $unit = config("pricing.units.{$resource}");

            if ($unit === null) {
                continue;
            }

            if ($limit === null) {
                $amount = (float) $unit['unlimited'];
                $blocks = null;
            } else {
                $blocks = (int) ceil($limit / max(1, (int) $unit['per']));
                $amount = $blocks * (float) $unit['price'];
            }

            $monthly += $amount;

            $lines[] = [
                'resource' => $resource,
                'limit' => $limit,
                'blocks' => $blocks,
                'amount' => round($amount, 2),
            ];
        }

        $featureLines = [];

        foreach (self::FEATURES as $feature) {
            $config = config("pricing.features.{$feature}");

            if ($config === null) {
                continue;
            }

            $enabled = (bool) ($features[$feature] ?? false);
            $amount = $enabled ? (float) $config['price'] : 0.0;

            $monthly += $amount;

            $featureLines[] = [
                'feature' => $feature,
                'label' => (string) $config['label'],
                'enabled' => $enabled,
                'amount' => round($amount, 2),
            ];
        }

        $monthly = round($monthly, 2);

        return [
            'monthly' => $monthly,
            'yearly' => round($monthly * (float) config('pricing.yearly_months', 12), 2),
            'base' => round($base, 2),
            'lines' => $lines,
            'features' => $featureLines,
        ];
    }

    /**
     * The rates themselves, for the screen that explains the quote.
     *
     * @return array<string, mixed>
     */
    public function rates(): array
    {
        return [
            'base_monthly' => (float) config('pricing.base_monthly', 0),
            'units' => config('pricing.units', []),
            'features' => config('pricing.features', []),
            'yearly_months' => (int) config('pricing.yearly_months', 12),
        ];
    }
}
