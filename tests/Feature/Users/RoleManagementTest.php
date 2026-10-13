<?php

namespace Tests\Feature\Users;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * docs/project.md F2 and §5.4 — roles and their permissions.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Role $systemStaffRole;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->systemStaffRole = Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Personnel',
            'slug' => 'staff', 'is_system' => true,
        ]);

        $this->organization = Organization::factory()->create();

        $adminRole = Role::factory()->create(['organization_id' => $this->organization->id]);
        $adminRole->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $adminRole->id,
        ]);
    }

    /**
     * The list shows only the organizations own roles.
     */
    public function test_the_list_shows_only_the_organizations_own_roles()
    {
        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('roles/index')
                ->has('roles', 1)
            );
    }

    /**
     * A role can be created with chosen permissions.
     */
    public function test_a_role_can_be_created_with_chosen_permissions()
    {
        $this->actingAs($this->admin)
            ->post(route('roles.store'), [
                'name' => 'Charge de communication',
                'description' => 'Gere les campagnes uniquement.',
                'permissions' => ['campaigns.view', 'campaigns.create', 'campaigns.send'],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success');

        $role = Role::withoutGlobalScopes()
            ->where('name', 'Charge de communication')->firstOrFail();

        $this->assertSame($this->organization->id, $role->organization_id);
        $this->assertFalse((bool) $role->is_system);
        $this->assertSame('charge-de-communication', $role->slug);
        $this->assertEqualsCanonicalizing(
            ['campaigns.view', 'campaigns.create', 'campaigns.send'],
            $role->permissions()->pluck('slug')->all(),
        );
    }

    /**
     * An unknown permission is rejected.
     */
    public function test_an_unknown_permission_is_rejected()
    {
        $this->actingAs($this->admin)
            ->post(route('roles.store'), [
                'name' => 'Role invalide',
                'permissions' => ['alumni.view', 'platform.takeover'],
            ])
            ->assertSessionHasErrors('permissions.1');

        $this->assertDatabaseMissing('roles', ['name' => 'Role invalide']);
    }

    /**
     * Permissions can be replaced on update.
     */
    public function test_permissions_can_be_replaced_on_update()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::whereIn('slug', ['alumni.view', 'alumni.create'])->pluck('id'));

        $this->actingAs($this->admin)
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'permissions' => ['events.view'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['events.view'], $role->refresh()->permissions()->pluck('slug')->all());
    }

    /**
     * Clearing every permission is allowed.
     */
    public function test_clearing_every_permission_is_allowed()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::whereIn('slug', ['alumni.view'])->pluck('id'));

        $this->actingAs($this->admin)
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'permissions' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $role->refresh()->permissions()->count());
    }

    /**
     * A platform template cannot be touched from an organization.
     */
    public function test_a_platform_template_cannot_be_touched_from_an_organization()
    {
        $this->actingAs($this->admin)
            ->get(route('roles.edit', $this->systemStaffRole))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put(route('roles.update', $this->systemStaffRole), [
                'name' => 'Personnel detourne',
                'permissions' => Permissions::slugs(),
            ])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $this->systemStaffRole))
            ->assertNotFound();

        $this->assertSame('Personnel', $this->systemStaffRole->refresh()->name);
    }

    /**
     * A role still held by a user cannot be deleted.
     */
    public function test_a_role_still_held_by_a_user_cannot_be_deleted()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    /**
     * An unused role goes to the corbeille.
     */
    public function test_an_unused_role_goes_to_the_corbeille()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
        $this->assertSame(0, Role::where('id', $role->id)->count());
    }

    /**
     * A role of another organization returns 404.
     */
    public function test_a_role_of_another_organization_returns_404()
    {
        $other = Organization::factory()->create();
        $foreign = Role::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($this->admin)->get(route('roles.edit', $foreign))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('roles.destroy', $foreign))->assertNotFound();
    }

    /**
     * Managing roles requires the manage permission.
     */
    public function test_managing_roles_requires_the_manage_permission()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::where('slug', 'roles.view')->pluck('id'));

        $viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($viewer)->get(route('roles.index'))->assertOk();
        $this->actingAs($viewer)->get(route('roles.create'))->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('roles.store'), ['name' => 'Refuse', 'permissions' => []])
            ->assertForbidden();
    }

    /**
     * Slugs do not collide within an organization.
     */
    public function test_slugs_do_not_collide_within_an_organization()
    {
        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->admin)->post(route('roles.store'), [
                'name' => 'Coordinateur',
                'permissions' => [],
            ])->assertSessionHasNoErrors();
        }

        $slugs = Role::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('name', 'Coordinateur')
            ->pluck('slug')
            ->all();

        $this->assertSame(['coordinateur', 'coordinateur-2'], $slugs);
    }
}
