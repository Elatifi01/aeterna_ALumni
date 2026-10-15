<?php

namespace Tests\Feature\Admin;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\PlanLimitService;
use App\Services\PricingCalculator;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Bespoke subscriptions (docs/project.md F6).
 */
class CustomSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Organization $organization;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $role = Role::factory()->create([
            'organization_id' => null, 'slug' => 'super-admin', 'is_system' => true, 'guard' => 'platform',
        ]);
        $role->permissions()->sync(Permission::whereIn('slug', Permissions::platformSlugs())->pluck('id'));
        $this->superAdmin = User::factory()->create([
            'organization_id' => null, 'role_id' => $role->id,
        ]);

        $this->organization = Organization::factory()->create(['name' => 'Universite Test']);

        config([
            'pricing.base_monthly' => 19.00,
            'pricing.units' => [
                'alumni' => ['per' => 100, 'price' => 4.00, 'unlimited' => 120.00],
                'events' => ['per' => 10, 'price' => 3.00, 'unlimited' => 40.00],
                'campaigns' => ['per' => 10, 'price' => 3.00, 'unlimited' => 40.00],
                'users' => ['per' => 1, 'price' => 2.00, 'unlimited' => 60.00],
            ],
            'pricing.features' => [
                'alumni_portal' => [
                    'label' => 'Portail alumni',
                    'description' => 'Comptes alumni',
                    'price' => 29.00,
                ],
            ],
            'pricing.yearly_months' => 10,
        ]);
    }

    /**
     * Post a bespoke subscription for the organization.
     */
    private function assign(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->superAdmin)->post(route('admin.subscriptions.store'), array_merge([
            'organization_id' => $this->organization->id,
            'mode' => 'custom',
            'name' => 'Sur mesure — Universite Test',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'limits' => ['alumni' => 500, 'events' => 20, 'campaigns' => 20, 'users' => 10],
        ], $overrides));
    }

    /**
     * The price is computed from the limits.
     */
    public function test_the_price_is_computed_from_the_limits()
    {
        $quote = app(PricingCalculator::class)->quote([
            'alumni' => 500, 'events' => 20, 'campaigns' => 20, 'users' => 10,
        ]);

        $this->assertSame(71.0, $quote['monthly']);
        $this->assertSame(710.0, $quote['yearly']);
    }

    /**
     * A part block is charged as a whole one.
     */
    public function test_a_part_block_is_charged_as_a_whole_one()
    {
        $quote = app(PricingCalculator::class)->quote([
            'alumni' => 101, 'events' => 1, 'campaigns' => 1, 'users' => 1,
        ]);

        $line = collect($quote['lines'])->firstWhere('resource', 'alumni');

        $this->assertSame(2, $line['blocks']);
        $this->assertSame(8.0, $line['amount']);
    }

    /**
     * A blank limit is priced as unlimited.
     */
    public function test_a_blank_limit_is_priced_as_unlimited()
    {
        $quote = app(PricingCalculator::class)->quote([
            'alumni' => null, 'events' => 10, 'campaigns' => 10, 'users' => 1,
        ]);

        $line = collect($quote['lines'])->firstWhere('resource', 'alumni');

        $this->assertNull($line['limit']);
        $this->assertSame(120.0, $line['amount']);
    }

    /**
     * A bespoke subscription creates its own plan and assigns it.
     */
    public function test_a_bespoke_subscription_creates_its_own_plan_and_assigns_it()
    {
        $this->assign()->assertRedirect(route('admin.subscriptions.index'));

        $plan = Plan::where('is_custom', true)->firstOrFail();

        $this->assertSame('Sur mesure — Universite Test', $plan->name);
        $this->assertSame('sur-mesure-'.$this->organization->slug, $plan->slug);
        $this->assertSame(500, $plan->max_alumni);
        $this->assertSame(10, $plan->max_users);
        $this->assertSame('71.00', $plan->price_monthly);
        $this->assertSame('710.00', $plan->price_yearly);
        $this->assertFalse($plan->is_active);

        $subscription = $this->organization->refresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('71.00', $subscription->price);
    }

    /**
     * The bespoke limits actually govern the organization.
     */
    public function test_the_bespoke_limits_actually_govern_the_organization()
    {
        $this->assign(['limits' => ['alumni' => 2, 'events' => 5, 'campaigns' => 5, 'users' => 5]]);

        $usage = app(PlanLimitService::class)->usageFor($this->organization->refresh(), 'alumni');

        $this->assertSame(2, $usage['limit']);

        Alumni::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $this->assertSame(0, app(PlanLimitService::class)
            ->usageFor($this->organization, 'alumni')['remaining']);
    }

    /**
     * A negotiated price overrides the quote.
     */
    public function test_a_negotiated_price_overrides_the_quote()
    {
        $this->assign(['price_override' => '49.50']);

        $plan = Plan::where('is_custom', true)->firstOrFail();

        $this->assertSame('49.50', $plan->price_monthly);
        $this->assertSame('710.00', $plan->price_yearly);
    }

    /**
     * A bespoke name is required.
     */
    public function test_a_bespoke_name_is_required()
    {
        $this->assign(['name' => ''])->assertSessionHasErrors('name');

        $this->assertSame(0, Plan::where('is_custom', true)->count());
    }

    /**
     * A bespoke plan stays off the catalogue.
     */
    public function test_a_bespoke_plan_stays_off_the_catalogue()
    {
        $this->assign();

        Plan::factory()->create(['name' => 'Pro', 'is_active' => true, 'is_custom' => false]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('plans', 1)
                ->where('plans.0.name', 'Pro')
                ->where('customPlansCount', 1)
            );
    }

    /**
     * A bespoke plan cannot be edited from the catalogue.
     */
    public function test_a_bespoke_plan_cannot_be_edited_from_the_catalogue()
    {
        $this->assign();
        $plan = Plan::where('is_custom', true)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.plans.edit', $plan))
            ->assertNotFound();

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertNotFound();
    }

    /**
     * A bespoke plan cannot be assigned to another organization.
     */
    public function test_a_bespoke_plan_cannot_be_assigned_to_another_organization()
    {
        $this->assign();
        $plan = Plan::where('is_custom', true)->firstOrFail();

        $other = Organization::factory()->create();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.subscriptions.store'), [
                'organization_id' => $other->id,
                'mode' => 'plan',
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('plan_id');
    }

    /**
     * The form shows a live quote.
     */
    public function test_the_form_shows_a_live_quote()
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.subscriptions.create', [
                'limits' => ['alumni' => 300, 'users' => 5],
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/subscriptions/create')
                ->where('quote.monthly', 121)
                ->where('limits.alumni', 300)
                ->where('limits.events', null)
            );
    }

    /**
     * A capability adds its own line to the quote.
     */
    public function test_a_capability_adds_its_own_line_to_the_quote()
    {
        $quote = app(PricingCalculator::class)->quote(
            ['alumni' => 500, 'events' => 20, 'campaigns' => 20, 'users' => 10],
            ['alumni_portal' => true],
        );

        $this->assertSame(100.0, $quote['monthly']);
        $this->assertSame(1000.0, $quote['yearly']);

        $line = collect($quote['features'])->firstWhere('feature', 'alumni_portal');
        $this->assertTrue($line['enabled']);
        $this->assertSame(29.0, $line['amount']);
    }

    /**
     * A capability left off costs nothing but is still listed.
     */
    public function test_a_capability_left_off_costs_nothing_but_is_still_listed()
    {
        $quote = app(PricingCalculator::class)->quote(
            ['alumni' => 500, 'events' => 20, 'campaigns' => 20, 'users' => 10],
        );

        $this->assertSame(71.0, $quote['monthly']);

        $line = collect($quote['features'])->firstWhere('feature', 'alumni_portal');
        $this->assertFalse($line['enabled']);
        $this->assertSame(0.0, $line['amount']);
    }

    /**
     * A bespoke plan records the chosen capabilities.
     */
    public function test_a_bespoke_plan_records_the_chosen_capabilities()
    {
        $this->assign(['features' => ['alumni_portal' => '1']]);

        $plan = Plan::where('is_custom', true)->firstOrFail();

        $this->assertTrue($plan->alumni_portal);
        $this->assertSame('100.00', $plan->price_monthly);

        $this->assertTrue(
            app(PlanLimitService::class)->allows($this->organization->refresh(), 'alumni_portal'),
        );
    }

    /**
     * A bespoke plan without the portal does not grant it.
     */
    public function test_a_bespoke_plan_without_the_portal_does_not_grant_it()
    {
        $this->assign();

        $plan = Plan::where('is_custom', true)->firstOrFail();

        $this->assertFalse($plan->alumni_portal);
        $this->assertFalse(
            app(PlanLimitService::class)->allows($this->organization->refresh(), 'alumni_portal'),
        );
    }

    /**
     * An offered plan still works.
     */
    public function test_an_offered_plan_still_works()
    {
        $plan = Plan::factory()->create([
            'is_active' => true, 'is_custom' => false, 'price_monthly' => 49,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.subscriptions.store'), [
                'organization_id' => $this->organization->id,
                'mode' => 'plan',
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($plan->id, $this->organization->refresh()->activeSubscription->plan_id);
        $this->assertSame(0, Plan::where('is_custom', true)->count());
    }
}
