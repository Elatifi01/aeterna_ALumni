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
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The free plan is a demo trial: granted by the Super Administrator for a set number of days, then the space is blocked.
 */
class DemoTrialTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Plan $free;

    private Plan $pro;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $platform = Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Super administrateur',
            'slug' => Role::PLATFORM_SLUG, 'is_system' => true, 'guard' => 'platform',
        ]);
        $platform->permissions()->sync(Permission::whereIn('slug', Permissions::platformSlugs())->pluck('id'));
        Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Administrateur', 'slug' => 'org-admin', 'is_system' => true,
        ]);

        $this->superAdmin = User::factory()->create(['organization_id' => null, 'role_id' => $platform->id]);

        $this->free = Plan::factory()->create([
            'name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'price_yearly' => 0,
            'is_active' => true, 'is_custom' => false,
        ]);
        $this->pro = Plan::factory()->create([
            'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 49, 'price_yearly' => 490,
            'is_active' => true, 'is_custom' => false,
        ]);
    }

    /**
     * An organization member with every staff permission.
     */
    private function memberOf(Organization $organization): User
    {
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->sync(Permission::whereIn('slug', Permissions::slugs())->pluck('id'));

        return User::factory()->create(['organization_id' => $organization->id, 'role_id' => $role->id]);
    }

    /**
     * An organization on the free plan whose trial ends on the given day.
     */
    private function onTrialUntil(string $endsAt): Organization
    {
        $organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => $this->free->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'price' => 0,
            'starts_at' => now()->subDays(30),
            'ends_at' => $endsAt,
        ]);

        return $organization;
    }

    /**
     * The organization form shows a trial length for the free plan.
     */
    public function test_the_super_admin_grants_a_free_trial_of_a_chosen_length()
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Lycee Descartes',
                'email' => 'contact@descartes.test',
                'admin_name' => 'Directeur',
                'admin_email' => 'admin@descartes.test',
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
                'plan_id' => $this->free->id,
                'trial_days' => 10,
            ])
            ->assertRedirect(route('admin.organizations.index'));

        $subscription = Organization::where('name', 'Lycee Descartes')->firstOrFail()->activeSubscription;

        $this->assertSame($this->free->id, $subscription->plan_id);
        $this->assertSame(now()->addDays(10)->toDateString(), $subscription->ends_at->toDateString());
    }

    /**
     * The trial length is required for the free plan, and bounded.
     */
    public function test_the_trial_length_is_required_and_bounded()
    {
        $form = [
            'name' => 'Lycee Descartes', 'email' => 'contact@descartes.test',
            'admin_name' => 'Directeur', 'admin_email' => 'admin@descartes.test',
            'admin_password' => 'motdepasse123', 'admin_password_confirmation' => 'motdepasse123',
            'plan_id' => $this->free->id,
        ];

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), $form)
            ->assertSessionHasErrors('trial_days');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), $form + ['trial_days' => 400])
            ->assertSessionHasErrors('trial_days');

        $this->assertSame(0, Organization::count());
    }

    /**
     * Once the trial is over, the organization's staff are held on the trial-ended page.
     */
    public function test_an_ended_trial_blocks_the_organization()
    {
        $member = $this->memberOf($this->onTrialUntil(now()->subDay()->toDateString()));

        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('trial.ended'));
        $this->actingAs($member)->get(route('alumni.index'))->assertRedirect(route('trial.ended'));

        $this->actingAs($member)
            ->get(route('trial.ended'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('trial-ended'));
    }

    /**
     * The last day of the trial still counts.
     */
    public function test_the_trial_is_usable_until_its_last_day()
    {
        $member = $this->memberOf($this->onTrialUntil(now()->toDateString()));

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
        $this->actingAs($member)->get(route('trial.ended'))->assertRedirect(route('dashboard'));
    }

    /**
     * The Super Administrator extends a trial, which unblocks the organization.
     */
    public function test_extending_the_trial_unblocks_the_organization()
    {
        $organization = $this->onTrialUntil(now()->subDays(3)->toDateString());
        $member = $this->memberOf($organization);

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.organizations.extend-trial', $organization), ['days' => 7])
            ->assertSessionHas('success');

        $subscription = Subscription::withoutGlobalScopes()->where('organization_id', $organization->id)->sole();
        $this->assertSame('active', $subscription->status);
        $this->assertSame(now()->addDays(7)->toDateString(), $subscription->ends_at->toDateString());

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }

    /**
     * Moving to a paid plan unblocks the organization.
     */
    public function test_a_paid_plan_unblocks_the_organization()
    {
        $organization = $this->onTrialUntil(now()->subDays(3)->toDateString());
        $member = $this->memberOf($organization);

        app(SubscriptionService::class)->assign($organization, $this->pro, 'monthly');

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }

    /**
     * Only a free trial can be extended from the organization screen.
     */
    public function test_a_paid_subscription_cannot_be_extended_as_a_trial()
    {
        $organization = Organization::factory()->create();
        app(SubscriptionService::class)->assign($organization, $this->pro, 'monthly');

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.organizations.extend-trial', $organization), ['days' => 7])
            ->assertNotFound();
    }

    /**
     * An organization that never had a subscription is not treated as an ended trial.
     */
    public function test_an_organization_without_subscription_is_not_blocked()
    {
        $member = $this->memberOf(Organization::factory()->create());

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }

    /**
     * The free plan cannot be taken from the landing page: it comes after a demo.
     */
    public function test_the_free_plan_is_not_self_served()
    {
        $this->get(route('signup.create', $this->free))->assertNotFound();
        $this->post(route('signup.store', $this->free), [])->assertNotFound();
    }
}
