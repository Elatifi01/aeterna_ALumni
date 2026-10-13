<?php

namespace Tests\Feature\Alumni;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimitService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * docs/project.md F6 — limits are enforced server-side before any create, and a clear message is returned when the limit is reached.
 */
class AlumniPlanLimitTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create();

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Subscribe the organization to a plan with this alumni limit.
     */
    private function subscribeTo(?int $maxAlumni): void
    {
        $plan = Plan::factory()->create(['max_alumni' => $maxAlumni]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    /**
     * Post a new alumni as the administrator.
     */
    private function createAlumni(string $email): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('alumni.store'), [
            'first_name' => 'Nouvel',
            'last_name' => 'Alumni',
            'email' => $email,
        ]);
    }

    /**
     * Creating is blocked once the plan limit is reached.
     */
    public function test_creating_is_blocked_once_the_plan_limit_is_reached()
    {
        $this->subscribeTo(2);

        $this->createAlumni('un@example.test')->assertSessionHasNoErrors();
        $this->createAlumni('deux@example.test')->assertSessionHasNoErrors();

        $response = $this->createAlumni('trois@example.test');

        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            'limite a 2 alumni',
            session('error'),
        );

        $this->assertSame(2, Alumni::withoutGlobalScopes()->count());
    }

    /**
     * A null limit means unlimited.
     */
    public function test_a_null_limit_means_unlimited()
    {
        $this->subscribeTo(null);

        foreach (range(1, 5) as $index) {
            $this->createAlumni("alumni{$index}@example.test")->assertSessionHasNoErrors();
        }

        $this->assertSame(5, Alumni::withoutGlobalScopes()->count());
    }

    /**
     * Soft deleted alumni free up allowance.
     */
    public function test_soft_deleted_alumni_free_up_allowance()
    {
        $this->subscribeTo(1);

        $this->createAlumni('premier@example.test');
        $this->createAlumni('deuxieme@example.test')->assertSessionHas('error');

        Alumni::firstOrFail()->delete();

        $this->createAlumni('deuxieme@example.test')->assertSessionMissing('error');

        $this->assertSame(1, Alumni::count());
        $this->assertSame(2, Alumni::withTrashed()->count());
    }

    /**
     * An organization with no active subscription cannot create.
     */
    public function test_an_organization_with_no_active_subscription_cannot_create()
    {
        $this->createAlumni('personne@example.test')->assertSessionHas('error');

        $this->assertSame(0, Alumni::withoutGlobalScopes()->count());
    }

    /**
     * An expired subscription grants no allowance.
     */
    public function test_an_expired_subscription_grants_no_allowance()
    {
        $plan = Plan::factory()->unlimited()->create();
        Subscription::factory()->expired()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
        ]);

        $this->createAlumni('expire@example.test')->assertSessionHas('error');
    }

    /**
     * Usage is reported for the subscription screen.
     */
    public function test_usage_is_reported_for_the_subscription_screen()
    {
        $this->subscribeTo(10);
        Alumni::factory()->count(4)->create(['organization_id' => $this->organization->id]);

        $usage = app(PlanLimitService::class)->usageFor($this->organization, 'alumni');

        $this->assertSame(4, $usage['used']);
        $this->assertSame(10, $usage['limit']);
        $this->assertSame(6, $usage['remaining']);
    }
}
