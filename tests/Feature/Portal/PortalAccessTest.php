<?php

namespace Tests\Feature\Portal;

use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The portal's boundaries (docs/project.md F9 and §4).
 */
class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Alumni $alumni;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create(['slug' => 'ecole-a']);
        $this->givePlan($this->organization, portal: true);

        $this->alumni = $this->accountFor($this->organization, 'alumni-a@example.test');
    }

    /**
     * Subscribe the organization to a plan.
     */
    private function givePlan(Organization $organization, bool $portal): Plan
    {
        $plan = Plan::factory()->create(['alumni_portal' => $portal]);

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        return $plan;
    }

    /**
     * An alumni of the organization with a portal account.
     */
    private function accountFor(Organization $organization, string $email, bool $mustChange = false): Alumni
    {
        $alumni = Alumni::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
        ]);

        $alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => $mustChange,
            'credentials_sent_at' => now(),
            'password_changed_at' => $mustChange ? null : now(),
        ])->save();

        return $alumni->refresh();
    }

    /**
     * Post the portal login form.
     */
    private function login(Alumni $alumni, string $password = 'motdepasse123'): TestResponse
    {
        $slug = $alumni->organization()->withoutGlobalScopes()->value('slug');

        return $this->post(route('portal.login.store', ['organization' => $slug]), [
            'email' => $alumni->email,
            'password' => $password,
        ]);
    }

    /**
     * An alumni can sign in and reach their dashboard.
     */
    public function test_an_alumni_can_sign_in_and_reach_their_dashboard()
    {
        $this->login($this->alumni)->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($this->alumni, 'alumni');

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/dashboard')
                ->where('auth.alumni.email', 'alumni-a@example.test')
            );
    }

    /**
     * The same email in two organizations resolves to the right record.
     */
    public function test_the_same_email_in_two_organizations_resolves_to_the_right_record()
    {
        $other = Organization::factory()->create(['slug' => 'ecole-b']);
        $this->givePlan($other, portal: true);

        $second = $this->accountFor($other, 'alumni-a@example.test');
        $second->forceFill(['password' => 'autremotdepasse'])->save();

        $this->post(route('portal.login.store', ['organization' => 'ecole-a']), [
            'email' => 'alumni-a@example.test',
            'password' => 'autremotdepasse',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('alumni');

        $this->post(route('portal.login.store', ['organization' => 'ecole-b']), [
            'email' => 'alumni-a@example.test',
            'password' => 'autremotdepasse',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($second, 'alumni');
    }

    /**
     * An alumni never sees another organizations events.
     */
    public function test_an_alumni_never_sees_another_organizations_events()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other, portal: true);

        Event::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Le notre',
            'status' => 'published',
        ]);

        $stranger = Event::factory()->create([
            'organization_id' => $other->id,
            'title' => 'Chez les autres',
            'status' => 'published',
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.events.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Le notre')
            );

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.events.show', $stranger))
            ->assertNotFound();

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.events.register', $stranger))
            ->assertNotFound();

        $this->assertDatabaseCount('alumni_event', 0);
    }

    /**
     * A draft event is not visible to alumni.
     */
    public function test_a_draft_event_is_not_visible_to_alumni()
    {
        $draft = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events.data', 0));

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.events.show', $draft))
            ->assertNotFound();
    }

    /**
     * An alumni cannot reach the staff or platform spaces.
     */
    public function test_an_alumni_cannot_reach_the_staff_or_platform_spaces()
    {
        $this->login($this->alumni);

        foreach ([
            'dashboard', 'alumni.index', 'events.index',
            'campaigns.index', 'users.index', 'roles.index',
            'trash.index', 'organization.edit', 'admin.dashboard',
        ] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }

        $this->assertAuthenticatedAs($this->alumni, 'alumni');
    }

    /**
     * A staff user cannot reach the portal.
     */
    public function test_a_staff_user_cannot_reach_the_portal()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('portal.dashboard'))
            ->assertRedirect();

        $this->assertGuest('alumni');
    }

    /**
     * Signing in on one guard does not authenticate the other.
     */
    public function test_signing_in_on_one_guard_does_not_authenticate_the_other()
    {
        $this->login($this->alumni);

        $this->assertAuthenticatedAs($this->alumni, 'alumni');
        $this->assertGuest('web');
    }

    /**
     * A downgraded plan locks the alumni out and ends the session.
     */
    public function test_a_downgraded_plan_locks_the_alumni_out_and_ends_the_session()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertOk();

        Plan::query()->update(['alumni_portal' => false]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        $this->assertGuest('alumni');
    }

    /**
     * The login screen says so when the plan has no portal.
     */
    public function test_the_login_screen_says_so_when_the_plan_has_no_portal()
    {
        Plan::query()->update(['alumni_portal' => false]);

        $this->get(route('portal.login', ['organization' => 'ecole-a']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/auth/unavailable')
            );

        $this->login($this->alumni)->assertSessionHasErrors('email');
        $this->assertGuest('alumni');
    }

    /**
     * An alumni without an account cannot sign in.
     */
    public function test_an_alumni_without_an_account_cannot_sign_in()
    {
        $noAccount = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'sans-compte@example.test',
        ]);

        $this->login($noAccount, 'nimportequoi')->assertSessionHasErrors('email');
        $this->assertGuest('alumni');
    }

    /**
     * Revoked access ends the session.
     */
    public function test_revoked_access_ends_the_session()
    {
        $this->alumni->forceFill(['portal_access' => false])->save();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        $this->assertGuest('alumni');
    }

    /**
     * A deactivated organization has no portal at all.
     */
    public function test_a_deactivated_organization_has_no_portal_at_all()
    {
        $this->organization->forceFill(['is_active' => false])->save();

        $this->get(route('portal.login', ['organization' => 'ecole-a']))->assertNotFound();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));
    }

    /**
     * Registering respects capacity and can be cancelled.
     */
    public function test_registering_respects_capacity_and_can_be_cancelled()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'capacity' => 1,
            'starts_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.events.register', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('alumni_event', [
            'alumni_id' => $this->alumni->id,
            'event_id' => $event->id,
            'status' => 'registered',
        ]);

        $second = $this->accountFor($this->organization, 'second@example.test');

        $this->actingAs($second, 'alumni')
            ->post(route('portal.events.register', $event))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('alumni_event', 1);

        $this->actingAs($this->alumni, 'alumni')
            ->delete(route('portal.events.cancel', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('alumni_event', [
            'alumni_id' => $this->alumni->id,
            'event_id' => $event->id,
            'status' => 'canceled',
        ]);
    }

    /**
     * A past event cannot be joined.
     */
    public function test_a_past_event_cannot_be_joined()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->subWeek(),
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.events.register', $event))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('alumni_event', 0);
    }

    /**
     * The portal never exposes another tenants data through the shared props.
     */
    public function test_the_portal_never_exposes_another_tenants_data_through_the_shared_props()
    {
        $other = Organization::factory()->create(['name' => 'Autre etablissement']);
        $this->givePlan($other, portal: true);
        Campaign::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.organization.id', $this->organization->id)
                ->where('auth.permissions', [])
                ->where('auth.isSuperAdmin', false)
                ->where('auth.user', null)
            );
    }

    /**
     * A provisional password expires.
     */
    public function test_a_provisional_password_expires()
    {
        $alumni = $this->accountFor($this->organization, 'vieux@example.test', mustChange: true);
        $alumni->forceFill(['credentials_sent_at' => now()->subDays(31)])->save();

        config(['portal.initial_password_expires_days' => 30]);

        $this->login($alumni)->assertSessionHasErrors('email');
        $this->assertGuest('alumni');

        config(['portal.initial_password_expires_days' => null]);

        $this->login($alumni)->assertRedirect();
        $this->assertAuthenticatedAs($alumni->refresh(), 'alumni');
    }

    /**
     * A chosen password never expires.
     */
    public function test_a_chosen_password_never_expires()
    {
        config(['portal.initial_password_expires_days' => 1]);

        $this->alumni->forceFill(['credentials_sent_at' => now()->subYear()])->save();

        $this->login($this->alumni)->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($this->alumni, 'alumni');
    }

    /**
     * Login is throttled.
     */
    public function test_login_is_throttled()
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($this->alumni, 'faux')->assertSessionHasErrors('email');
        }

        $response = $this->login($this->alumni, 'motdepasse123');

        $response->assertSessionHasErrors('email');
        $this->assertGuest('alumni');
    }

    /**
     * Signing out returns to the organizations login page.
     */
    public function test_signing_out_returns_to_the_organizations_login_page()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.logout'))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        $this->assertGuest('alumni');
    }

    /**
     * The password is stored hashed.
     */
    public function test_the_password_is_stored_hashed()
    {
        $this->assertNotSame('motdepasse123', $this->alumni->password);
        $this->assertTrue(Hash::check('motdepasse123', (string) $this->alumni->password));
    }
}
