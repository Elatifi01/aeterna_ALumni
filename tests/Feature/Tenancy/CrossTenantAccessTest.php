<?php

namespace Tests\Feature\Tenancy;

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
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The isolation sweep the roadmap asks for (§3.8): a user of organization A can never read or write data of organization B.
 */
class CrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    /** @var array<string, mixed> Records belonging to organization B. */
    private array $theirs = [];

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Administrateur',
            'slug' => 'org-admin', 'is_system' => true,
        ]);

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        foreach ([$this->orgA, $this->orgB] as $organization) {
            Subscription::factory()->create([
                'organization_id' => $organization->id,
                'plan_id' => Plan::factory()->unlimited()->create()->id,
                'status' => 'active',
            ]);
        }

        $roleA = Role::factory()->create(['organization_id' => $this->orgA->id]);
        $roleA->permissions()->sync(Permission::pluck('id'));

        $this->adminA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role_id' => $roleA->id,
        ]);

        $roleB = Role::factory()->create(['organization_id' => $this->orgB->id]);

        $this->theirs = [
            'alumni' => Alumni::factory()->create(['organization_id' => $this->orgB->id]),
            'event' => Event::factory()->create(['organization_id' => $this->orgB->id]),
            'campaign' => Campaign::factory()->create(['organization_id' => $this->orgB->id]),
            'user' => User::factory()->create([
                'organization_id' => $this->orgB->id,
                'role_id' => $roleB->id,
            ]),
            'role' => $roleB,
        ];
    }

    /**
     * Every read route, addressed at organization B's record.
     *
     * @return array<string, array{string, string}>
     */
    public static function foreignReadRoutes(): array
    {
        return [
            'alumni show' => ['alumni.show', 'alumni'],
            'alumni edit' => ['alumni.edit', 'alumni'],
            'event show' => ['events.show', 'event'],
            'event edit' => ['events.edit', 'event'],
            'campaign show' => ['campaigns.show', 'campaign'],
            'campaign edit' => ['campaigns.edit', 'campaign'],
            'user edit' => ['users.edit', 'user'],
            'role edit' => ['roles.edit', 'role'],
        ];
    }

    #[DataProvider('foreignReadRoutes')]
    /**
     * Reading another organizations record is not found.
     */
    public function test_reading_another_organizations_record_is_not_found(string $route, string $key): void
    {
        $this->actingAs($this->adminA)
            ->get(route($route, $this->theirs[$key]))
            ->assertNotFound();
    }

    /**
     * Deleting another organizations records is not found.
     */
    public function test_deleting_another_organizations_records_is_not_found(): void
    {
        foreach ([
            ['alumni.destroy', 'alumni'],
            ['events.destroy', 'event'],
            ['campaigns.destroy', 'campaign'],
            ['users.destroy', 'user'],
            ['roles.destroy', 'role'],
        ] as [$route, $key]) {
            $this->actingAs($this->adminA)
                ->delete(route($route, $this->theirs[$key]))
                ->assertNotFound();
        }

        $this->assertNotSoftDeleted('alumni', ['id' => $this->theirs['alumni']->id]);
        $this->assertNotSoftDeleted('events', ['id' => $this->theirs['event']->id]);
        $this->assertNotSoftDeleted('campaigns', ['id' => $this->theirs['campaign']->id]);
        $this->assertNotSoftDeleted('users', ['id' => $this->theirs['user']->id]);
        $this->assertDatabaseHas('roles', ['id' => $this->theirs['role']->id]);
    }

    /**
     * Updating another organizations records is not found.
     */
    public function test_updating_another_organizations_records_is_not_found(): void
    {
        $originalName = $this->theirs['alumni']->first_name;
        $originalTitle = $this->theirs['event']->title;

        $this->actingAs($this->adminA)
            ->put(route('alumni.update', $this->theirs['alumni']), [
                'first_name' => 'Pirate', 'last_name' => 'Nom', 'email' => 'pirate@example.test',
            ])
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->put(route('events.update', $this->theirs['event']), [
                'title' => 'Detourne', 'starts_at' => '2027-01-01T10:00', 'status' => 'draft',
            ])
            ->assertNotFound();

        $this->assertSame(
            $originalName,
            Alumni::withoutGlobalScopes()->find($this->theirs['alumni']->id)->first_name,
        );
        $this->assertSame(
            $originalTitle,
            Event::withoutGlobalScopes()->find($this->theirs['event']->id)->title,
        );
    }

    /**
     * A foreign alumni cannot be registered to our event.
     */
    public function test_a_foreign_alumni_cannot_be_registered_to_our_event(): void
    {
        $ourEvent = Event::factory()->create(['organization_id' => $this->orgA->id]);

        $this->actingAs($this->adminA)
            ->post(route('events.registrations.store', $ourEvent), [
                'alumni_id' => $this->theirs['alumni']->id,
            ])
            ->assertSessionHasErrors('alumni_id');

        $this->assertDatabaseMissing('alumni_event', [
            'event_id' => $ourEvent->id,
            'alumni_id' => $this->theirs['alumni']->id,
        ]);
    }

    /**
     * A campaign never reaches another organizations alumni.
     */
    public function test_a_campaign_never_reaches_another_organizations_alumni(): void
    {
        Alumni::factory()->count(2)->create(['organization_id' => $this->orgA->id]);

        $this->actingAs($this->adminA)
            ->post(route('campaigns.store'), [
                'subject' => 'Notre campagne',
                'content' => 'Bonjour',
                'recipients' => 'all',
            ])
            ->assertSessionHasNoErrors();

        $campaign = Campaign::withoutGlobalScopes()
            ->where('organization_id', $this->orgA->id)->latest('id')->firstOrFail();

        $this->assertSame(2, $campaign->recipients_count);
        $this->assertNotContains(
            $this->theirs['alumni']->id,
            $campaign->recipients()->pluck('alumni.id')->all(),
        );
    }

    /**
     * A foreign role cannot be assigned to our user.
     */
    public function test_a_foreign_role_cannot_be_assigned_to_our_user(): void
    {
        $this->actingAs($this->adminA)
            ->post(route('users.store'), [
                'name' => 'Nouveau',
                'email' => 'nouveau@example.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $this->theirs['role']->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'nouveau@example.test']);
    }

    /**
     * Lists only ever show our own records.
     */
    public function test_lists_only_ever_show_our_own_records(): void
    {
        Alumni::factory()->count(3)->create(['organization_id' => $this->orgA->id]);
        Event::factory()->count(2)->create(['organization_id' => $this->orgA->id]);
        Campaign::factory()->count(4)->create(['organization_id' => $this->orgA->id]);

        $this->actingAs($this->adminA)->get(route('alumni.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('alumni.total', 3));

        $this->actingAs($this->adminA)->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('events.total', 2));

        $this->actingAs($this->adminA)->get(route('campaigns.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('campaigns.total', 4));

        $this->actingAs($this->adminA)->get(route('users.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('users.total', 1));
    }

    /**
     * The dashboard counts only our own records.
     */
    public function test_the_dashboard_counts_only_our_own_records(): void
    {
        Alumni::factory()->count(5)->create(['organization_id' => $this->orgA->id]);
        Alumni::factory()->count(9)->create(['organization_id' => $this->orgB->id]);

        $this->actingAs($this->adminA)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.alumni', 5));
    }

    /**
     * The participation rate ignores another organizations attendance.
     */
    public function test_the_participation_rate_ignores_another_organizations_attendance(): void
    {
        $ourEvent = Event::factory()->create(['organization_id' => $this->orgA->id]);
        $ourAlumni = Alumni::factory()->create(['organization_id' => $this->orgA->id]);
        $ourEvent->alumni()->attach($ourAlumni->id, [
            'status' => 'registered', 'registered_at' => now(), 'attended' => false,
        ]);

        $this->theirs['event']->alumni()->attach($this->theirs['alumni']->id, [
            'status' => 'registered', 'registered_at' => now(), 'attended' => true,
        ]);

        $this->actingAs($this->adminA)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.participationRate', 0));
    }

    /**
     * Plan usage is not inflated by another organization.
     */
    public function test_plan_usage_is_not_inflated_by_another_organization(): void
    {
        Alumni::factory()->count(2)->create(['organization_id' => $this->orgA->id]);
        Alumni::factory()->count(30)->create(['organization_id' => $this->orgB->id]);

        $this->actingAs($this->adminA)
            ->get(route('alumni.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('usage.used', 2));
    }

    /**
     * The corbeille never shows another organizations records.
     */
    public function test_the_corbeille_never_shows_another_organizations_records(): void
    {
        $ours = Alumni::factory()->create(['organization_id' => $this->orgA->id]);
        $ours->delete();
        $this->theirs['alumni']->delete();

        $this->actingAs($this->adminA)
            ->get(route('trash.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $ours->id)
                ->where('counts.alumni', 1)
            );
    }

    /**
     * An organization user cannot reach the platform space.
     */
    public function test_an_organization_user_cannot_reach_the_platform_space(): void
    {
        foreach ([
            'admin.dashboard',
            'admin.organizations.index',
            'admin.plans.index',
            'admin.subscriptions.index',
        ] as $route) {
            $this->actingAs($this->adminA)->get(route($route))->assertForbidden();
        }
    }

    /**
     * A deactivated organization locks its users out.
     */
    public function test_a_deactivated_organization_locks_its_users_out(): void
    {
        $this->orgA->update(['is_active' => false]);

        $this->actingAs($this->adminA)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->adminA)->get(route('alumni.index'))->assertForbidden();
    }
}
