<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * docs/project.md F1 and F6 — the platform space.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $superAdminRole = Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Super administrateur',
            'slug' => 'super-admin', 'is_system' => true, 'guard' => 'platform',
        ]);
        $superAdminRole->permissions()->sync(Permission::whereIn('slug', Permissions::platformSlugs())->pluck('id'));
        Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Administrateur',
            'slug' => 'org-admin', 'is_system' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'organization_id' => null,
            'role_id' => $superAdminRole->id,
        ]);
    }

    /**
     * The platform space is closed to organization users.
     */
    public function test_the_platform_space_is_closed_to_organization_users()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => Role::factory()->create(['organization_id' => $organization->id])->id,
        ]);

        foreach ([
            route('admin.organizations.index'),
            route('admin.plans.index'),
            route('admin.subscriptions.index'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    /**
     * Creating an organization also creates its first administrator.
     */
    public function test_creating_an_organization_also_creates_its_first_administrator()
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Universite Mohammed V',
                'email' => 'contact@um5.test',
                'admin_name' => 'Directrice des relations alumni',
                'admin_email' => 'admin@um5.test',
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success');

        $organization = Organization::where('name', 'Universite Mohammed V')->firstOrFail();
        $this->assertSame('universite-mohammed-v', $organization->slug);

        $admin = User::withoutGlobalScopes()->where('email', 'admin@um5.test')->firstOrFail();
        $this->assertSame($organization->id, $admin->organization_id);
        $this->assertSame('org-admin', $admin->role->slug);
        $this->assertTrue(Hash::check('motdepasse123', $admin->password));

        $subscription = $organization->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame($plan->price_monthly, $subscription->price);
    }

    /**
     * An organization can be created without a subscription.
     */
    public function test_an_organization_can_be_created_without_a_subscription()
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Sans abonnement',
                'email' => 'contact@sans.test',
                'admin_name' => 'Admin',
                'admin_email' => 'admin@sans.test',
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
            ])
            ->assertSessionHasNoErrors();

        $organization = Organization::where('name', 'Sans abonnement')->firstOrFail();
        $this->assertNull($organization->activeSubscription);
    }

    /**
     * Choosing a plan requires a billing cycle.
     */
    public function test_choosing_a_plan_requires_a_billing_cycle()
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Incomplet',
                'email' => 'contact@incomplet.test',
                'admin_name' => 'Admin',
                'admin_email' => 'admin@incomplet.test',
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
                'plan_id' => $plan->id,
            ])
            ->assertSessionHasErrors('billing_cycle');
    }

    /**
     * Slugs do not collide.
     */
    public function test_slugs_do_not_collide()
    {
        foreach (['admin1@x.test', 'admin2@x.test'] as $email) {
            $this->actingAs($this->superAdmin)->post(route('admin.organizations.store'), [
                'name' => 'Ecole Superieure',
                'email' => 'contact@es.test',
                'admin_name' => 'Admin',
                'admin_email' => $email,
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['ecole-superieure', 'ecole-superieure-2'],
            Organization::where('name', 'Ecole Superieure')->orderBy('id')->pluck('slug')->all(),
        );
    }

    /**
     * An organization can be deactivated and its users locked out.
     */
    public function test_an_organization_can_be_deactivated_and_its_users_locked_out()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => Role::factory()->create(['organization_id' => $organization->id])->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.organizations.toggle-active', $organization))
            ->assertSessionHas('success');

        $this->assertFalse($organization->refresh()->is_active);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    /**
     * Assigning a subscription replaces the previous active one.
     */
    public function test_assigning_a_subscription_replaces_the_previous_active_one()
    {
        $organization = Organization::factory()->create();
        $free = Plan::factory()->create(['is_active' => true, 'price_monthly' => 0]);
        $pro = Plan::factory()->create(['is_active' => true, 'price_monthly' => 49]);

        $service = app(SubscriptionService::class);
        $first = $service->assign($organization, $free, 'monthly');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.subscriptions.store'), [
                'organization_id' => $organization->id,
                'mode' => 'plan',
                'plan_id' => $pro->id,
                'billing_cycle' => 'yearly',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect(route('admin.subscriptions.index'));

        $this->assertSame('expired', $first->refresh()->status);
        $this->assertSame(
            1,
            Subscription::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->count(),
        );
        $this->assertSame($pro->id, $organization->refresh()->activeSubscription->plan_id);
    }

    /**
     * The end date must follow the start date.
     */
    public function test_the_end_date_must_follow_the_start_date()
    {
        $organization = Organization::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.subscriptions.store'), [
                'organization_id' => $organization->id,
                'mode' => 'plan',
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    /**
     * An overdue subscription is expired when the list is opened.
     */
    public function test_an_overdue_subscription_is_expired_when_the_list_is_opened()
    {
        $organization = Organization::factory()->create();
        $subscription = Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::factory()->create()->id,
            'status' => 'active',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.subscriptions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/subscriptions/index'));

        $this->assertSame('expired', $subscription->refresh()->status);
    }

    /**
     * A subscription can be cancelled and keeps its row.
     */
    public function test_a_subscription_can_be_cancelled_and_keeps_its_row()
    {
        $organization = Organization::factory()->create();
        $subscription = app(SubscriptionService::class)
            ->assign($organization, Plan::factory()->create(['is_active' => true]), 'monthly');

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.subscriptions.cancel', $subscription))
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    /**
     * A plan can be created with limits and paddle prices.
     */
    public function test_a_plan_can_be_created_with_limits_and_paddle_prices()
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.plans.store'), [
                'name' => 'Entreprise',
                'description' => 'Pour les grands reseaux.',
                'price_monthly' => 149,
                'price_yearly' => 1490,
                'max_alumni' => null,
                'max_events' => null,
                'max_campaigns' => null,
                'max_users' => 50,
                'paddle_price_monthly_id' => 'pri_monthly_123',
                'paddle_price_yearly_id' => 'pri_yearly_123',
            ])
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success');

        $plan = Plan::where('name', 'Entreprise')->firstOrFail();

        $this->assertSame('entreprise', $plan->slug);
        $this->assertNull($plan->max_alumni);
        $this->assertSame(50, $plan->max_users);
        $this->assertSame('pri_monthly_123', $plan->paddle_price_monthly_id);
    }

    /**
     * A plan that has been sold cannot be deleted.
     */
    public function test_a_plan_that_has_been_sold_cannot_be_deleted()
    {
        $organization = Organization::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true]);
        app(SubscriptionService::class)->assign($organization, $plan, 'monthly');

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    /**
     * An unsold plan can be deleted.
     */
    public function test_an_unsold_plan_can_be_deleted()
    {
        $plan = Plan::factory()->create();

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    /**
     * The super administrator sees every organization.
     */
    public function test_the_super_administrator_sees_every_organization()
    {
        Organization::factory()->count(4)->create();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/organizations/index')
                ->where('organizations.total', 4)
            );

        $this->assertSame(4, Organization::count());
    }
}
