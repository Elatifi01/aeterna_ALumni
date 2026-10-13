<?php

namespace Tests\Feature\Tenancy;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan usage counts respect soft deletes.
 */
class PlanLimitScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Soft deleted records do not count towards the limit.
     */
    public function test_soft_deleted_records_do_not_count_towards_the_limit()
    {
        $organization = Organization::factory()->create();
        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::factory()->create(['max_alumni' => 10])->id,
            'status' => 'active',
        ]);

        Alumni::factory()->count(3)->create(['organization_id' => $organization->id]);
        Alumni::factory()->create(['organization_id' => $organization->id])->delete();

        $usage = app(PlanLimitService::class)->usageFor($organization, 'alumni');

        $this->assertSame(3, $usage['used']);
    }

    /**
     * Usage is counted per organization.
     */
    public function test_usage_is_counted_per_organization()
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $mine->id,
            'plan_id' => Plan::factory()->create(['max_alumni' => 10])->id,
            'status' => 'active',
        ]);

        Alumni::factory()->count(2)->create(['organization_id' => $mine->id]);
        Alumni::factory()->count(7)->create(['organization_id' => $theirs->id]);

        $this->assertSame(2, app(PlanLimitService::class)->usageFor($mine, 'alumni')['used']);
    }
}
