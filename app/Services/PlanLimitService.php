<?php

namespace App\Services;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use InvalidArgumentException;

/**
 * Enforces the per-plan limits of docs/project.md F6.
 */
class PlanLimitService
{
    /** @var list<string> */
    public const CAPABILITIES = ['alumni_portal'];

    private const RESOURCES = [
        'alumni' => ['column' => 'max_alumni', 'label' => 'alumni'],
        'events' => ['column' => 'max_events', 'label' => 'evenements'],
        'campaigns' => ['column' => 'max_campaigns', 'label' => 'campagnes'],
        'users' => ['column' => 'max_users', 'label' => 'utilisateurs'],
    ];

    /**
     * Throw when creating one more of this resource would exceed the plan.
     *
     * @throws PlanLimitReachedException
     */
    public function ensureCanCreate(Organization $organization, string $resource): void
    {
        $usage = $this->usageFor($organization, $resource);

        if ($usage['limit'] === null) {
            return;
        }

        if ($usage['used'] < $usage['limit']) {
            return;
        }

        throw new PlanLimitReachedException(
            $resource,
            $usage['limit'],
            __('Votre plan est limite a :limit :resource. Passez a un plan superieur pour en ajouter davantage.', [
                'limit' => $usage['limit'],
                'resource' => self::RESOURCES[$resource]['label'],
            ]),
        );
    }

    /**
     * Whether the organization's plan includes a capability (docs/project.md F6).
     */
    public function allows(Organization $organization, string $capability): bool
    {
        if (! in_array($capability, self::CAPABILITIES, true)) {
            throw new InvalidArgumentException("Unknown plan capability [{$capability}].");
        }

        return (bool) ($this->planFor($organization)->{$capability} ?? false);
    }

    /**
     * Current usage and allowance for one resource.
     *
     * @return array{used: int, limit: int|null, remaining: int|null}
     */
    public function usageFor(Organization $organization, string $resource): array
    {
        $plan = $this->planFor($organization);
        $used = $this->countFor($organization, $resource);

        $limit = $plan === null
            ? 0
            : $plan->{self::RESOURCES[$resource]['column']};

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }

    /**
     * Usage across every limited resource, for the subscription screen.
     *
     * @return array<string, array{used: int, limit: int|null, remaining: int|null}>
     */
    public function usage(Organization $organization): array
    {
        $usage = [];

        foreach (array_keys(self::RESOURCES) as $resource) {
            $usage[$resource] = $this->usageFor($organization, $resource);
        }

        return $usage;
    }

    /**
     * The plan of the organization's active subscription, or null.
     */
    private function planFor(Organization $organization): ?Plan
    {
        return $organization->activeSubscription?->plan;
    }

    /**
     * How many of this resource the organization currently has.
     */
    private function countFor(Organization $organization, string $resource): int
    {
        $where = fn ($query) => $query->where('organization_id', $organization->id);

        return match ($resource) {
            'alumni' => Alumni::acrossOrganizations()->tap($where)->count(),
            'events' => Event::acrossOrganizations()->tap($where)->count(),
            'campaigns' => Campaign::acrossOrganizations()->tap($where)->count(),
            'users' => User::acrossOrganizations()->tap($where)->count(),
            default => throw new InvalidArgumentException("Unknown plan resource [{$resource}]."),
        };
    }
}
