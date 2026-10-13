<?php

namespace Tests\Feature\Trash;

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
use Tests\TestCase;

/**
 * The recycle bin.
 */
class TrashTest extends TestCase
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

        $this->subscribe(null);
    }

    /**
     * Subscribe the organization to a plan with this alumni limit.
     */
    private function subscribe(?int $maxAlumni): void
    {
        Subscription::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)->delete();

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->create([
                'max_alumni' => $maxAlumni,
                'max_events' => null,
                'max_campaigns' => null,
                'max_users' => null,
            ])->id,
            'status' => 'active',
        ]);
    }

    /**
     * The bin lists deleted records of every type.
     */
    public function test_the_bin_lists_deleted_records_of_every_type()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $event = Event::factory()->create(['organization_id' => $this->organization->id]);
        $campaign = Campaign::factory()->create(['organization_id' => $this->organization->id]);

        $alumni->delete();
        $event->delete();
        $campaign->delete();

        $this->actingAs($this->admin)
            ->get(route('trash.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('corbeille/index')
                ->has('items', 3)
                ->where('counts.alumni', 1)
                ->where('counts.events', 1)
                ->where('counts.campaigns', 1)
                ->where('counts.users', 0)
            );
    }

    /**
     * Live records never appear in the bin.
     */
    public function test_live_records_never_appear_in_the_bin()
    {
        Alumni::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->get(route('trash.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items', 0));
    }

    /**
     * A deleted alumni can be restored.
     */
    public function test_a_deleted_alumni_can_be_restored()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $alumni->delete();

        $this->assertSame(0, Alumni::count());

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['alumni', $alumni->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('alumni', ['id' => $alumni->id]);
        $this->assertSame(1, Alumni::count());
    }

    /**
     * A binned alumni keeps its email reserved.
     */
    public function test_a_binned_alumni_keeps_its_email_reserved()
    {
        $alumni = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'reprise@example.test',
        ]);
        $alumni->delete();

        $this->actingAs($this->admin)
            ->post(route('alumni.store'), [
                'first_name' => 'Quelqu un',
                'last_name' => 'Autre',
                'email' => 'reprise@example.test',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['alumni', $alumni->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('alumni', ['id' => $alumni->id]);
    }

    /**
     * Restoring is refused when the plan is full again.
     */
    public function test_restoring_is_refused_when_the_plan_is_full_again()
    {
        $this->subscribe(maxAlumni: 1);

        $deleted = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $deleted->delete();

        Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['alumni', $deleted->id]))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('alumni', ['id' => $deleted->id]);
    }

    /**
     * A record can be deleted for good.
     */
    public function test_a_record_can_be_deleted_for_good()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $alumni->delete();

        $this->actingAs($this->admin)
            ->delete(route('trash.force-delete', ['alumni', $alumni->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('alumni', ['id' => $alumni->id]);
    }

    /**
     * Another organizations deleted record is not reachable.
     */
    public function test_another_organizations_deleted_record_is_not_reachable()
    {
        $other = Organization::factory()->create();
        $foreign = Alumni::factory()->create(['organization_id' => $other->id]);
        $foreign->delete();

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['alumni', $foreign->id]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('trash.force-delete', ['alumni', $foreign->id]))
            ->assertNotFound();

        $this->assertSoftDeleted('alumni', ['id' => $foreign->id]);
    }

    /**
     * An unknown type is rejected.
     */
    public function test_an_unknown_type_is_rejected()
    {
        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['plans', 1]))
            ->assertNotFound();
    }

    /**
     * Restoring requires the delete permission for that resource.
     */
    public function test_restoring_requires_the_delete_permission_for_that_resource()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['alumni.view', 'events.view', 'events.delete'])->pluck('id'),
        );

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $alumni->delete();

        $this->actingAs($user)
            ->patch(route('trash.restore', ['alumni', $alumni->id]))
            ->assertForbidden();

        $this->assertSoftDeleted('alumni', ['id' => $alumni->id]);
    }

    /**
     * A type the user cannot view is left out of the bin.
     */
    public function test_a_type_the_user_cannot_view_is_left_out_of_the_bin()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::whereIn('slug', ['events.view', 'events.delete'])->pluck('id'));

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $alumni->delete();
        $event = Event::factory()->create(['organization_id' => $this->organization->id]);
        $event->delete();

        $this->actingAs($user)
            ->get(route('trash.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.type', 'events')
                ->where('counts.alumni', 0)
            );
    }

    /**
     * The bin can be filtered by type and searched.
     */
    public function test_the_bin_can_be_filtered_by_type_and_searched()
    {
        $alumni = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Yasmine', 'last_name' => 'Alaoui',
        ]);
        $alumni->delete();

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Gala annuel',
        ]);
        $event->delete();

        $this->actingAs($this->admin)
            ->get(route('trash.index', ['type' => 'events']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items', 1));

        $this->actingAs($this->admin)
            ->get(route('trash.index', ['search' => 'Alaoui']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.type', 'alumni')
            );
    }

    /**
     * A deleted user can be restored.
     */
    public function test_a_deleted_user_can_be_restored()
    {
        $colleague = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $this->admin->role_id,
        ]);
        $colleague->delete();

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['users', $colleague->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('users', ['id' => $colleague->id]);
    }

    /**
     * A deleted role can be restored.
     */
    public function test_a_deleted_role_can_be_restored()
    {
        $role = Role::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Charge de communication',
        ]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['campaigns.view', 'campaigns.send'])->pluck('id'),
        );
        $role->delete();

        $this->actingAs($this->admin)
            ->get(route('trash.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('counts.roles', 1)
                ->where('items.0.type', 'roles')
                ->where('items.0.label', 'Charge de communication')
            );

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['roles', $role->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('roles', ['id' => $role->id]);
        $this->assertSame(2, $role->refresh()->permissions()->count());
    }

    /**
     * A binned role cannot be assigned to a user.
     */
    public function test_a_binned_role_cannot_be_assigned_to_a_user()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->delete();

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Nouveau',
                'email' => 'nouveau@example.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'nouveau@example.test']);
    }

    /**
     * A role still held by a binned user cannot be deleted.
     */
    public function test_a_role_still_held_by_a_binned_user_cannot_be_deleted()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $holder = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
        $holder->delete();

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('roles', ['id' => $role->id]);
    }

    /**
     * A role can be deleted for good from the bin.
     */
    public function test_a_role_can_be_deleted_for_good_from_the_bin()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->delete();

        $this->actingAs($this->admin)
            ->delete(route('trash.force-delete', ['roles', $role->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
