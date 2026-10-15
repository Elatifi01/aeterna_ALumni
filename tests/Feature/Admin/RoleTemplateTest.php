<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleProvisioner;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The platform roles: the Super Administrator defines them, each new organization receives its own copy.
 */
class RoleTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Role $staffTemplate;

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
            'slug' => Role::PLATFORM_SLUG, 'is_system' => true, 'guard' => 'platform',
        ]);
        $superAdminRole->permissions()->sync(Permission::whereIn('slug', Permissions::platformSlugs())->pluck('id'));
        Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Administrateur',
            'slug' => 'org-admin', 'is_system' => true,
        ]);
        $this->staffTemplate = Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Personnel',
            'slug' => 'staff', 'is_system' => true,
        ]);
        Role::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => null, 'slug' => Permissions::ALUMNI_ROLE_SLUG],
            ['name' => 'Alumni', 'is_system' => true, 'guard' => 'alumni'],
        );

        $this->superAdmin = User::factory()->create([
            'organization_id' => null,
            'role_id' => $superAdminRole->id,
        ]);
    }

    /**
     * The Super Administrator sees the platform roles and none of the organizations' own.
     */
    public function test_the_super_admin_lists_only_the_platform_roles()
    {
        $organization = Organization::factory()->create();
        Role::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/roles/index')
                ->has('roles', 4)
            );
    }

    /**
     * The Super Administrator creates a platform role with chosen permissions.
     */
    public function test_the_super_admin_creates_a_platform_role()
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.roles.store'), [
                'name' => 'Charge de communication',
                'description' => 'Campagnes uniquement.',
                'permissions' => ['campaigns.view', 'campaigns.send'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::withoutGlobalScopes()->where('name', 'Charge de communication')->firstOrFail();

        $this->assertNull($role->organization_id);
        $this->assertTrue($role->is_system);
        $this->assertSame('web', $role->guard);
        $this->assertEqualsCanonicalizing(
            ['campaigns.view', 'campaigns.send'],
            $role->permissions()->pluck('slug')->all(),
        );
    }

    /**
     * The Super Administrator edits a platform role's permissions.
     */
    public function test_the_super_admin_edits_a_platform_role()
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.roles.edit', $this->staffTemplate))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.roles.update', $this->staffTemplate), [
                'name' => 'Personnel',
                'permissions' => ['alumni.view', 'events.view'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertEqualsCanonicalizing(
            ['alumni.view', 'events.view'],
            $this->staffTemplate->permissions()->pluck('slug')->all(),
        );
    }

    /**
     * The Super Administrator edits their own role from the platform catalogue, but can never delete it.
     */
    public function test_the_super_admin_role_is_editable_but_not_deletable()
    {
        $role = Role::withoutGlobalScopes()->where('slug', Role::PLATFORM_SLUG)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('catalogue.0.module', 'platform')
            );

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();

        $this->assertNotNull($role->fresh());
    }

    /**
     * Removing a platform permission hides the link and closes the page; the roles screen stays open.
     */
    public function test_removing_a_platform_permission_closes_that_part_of_the_platform()
    {
        $role = Role::withoutGlobalScopes()->where('slug', Role::PLATFORM_SLUG)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Super administrateur',
                'permissions' => ['platform.organizations', 'platform.subscriptions'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->actingAs($this->superAdmin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.permissions', ['platform.organizations', 'platform.subscriptions'])
            );

        $this->actingAs($this->superAdmin)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('admin.organizations.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('admin.roles.index'))->assertOk();
    }

    /**
     * A staff permission cannot be slipped into the Super Administrator role.
     */
    public function test_the_super_admin_role_only_takes_platform_permissions()
    {
        $role = Role::withoutGlobalScopes()->where('slug', Role::PLATFORM_SLUG)->firstOrFail();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Super administrateur',
                'permissions' => ['alumni.view'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    /**
     * The default roles every organization relies on cannot be deleted; a custom platform role can.
     */
    public function test_only_custom_platform_roles_can_be_deleted()
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('admin.roles.destroy', $this->staffTemplate))
            ->assertForbidden();

        $custom = Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Lecteur', 'slug' => 'lecteur', 'is_system' => true,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.roles.destroy', $custom))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertNull(Role::withoutGlobalScopes()->withTrashed()->find($custom->id));
    }

    /**
     * An organization's own role is not reachable from the platform screens.
     */
    public function test_an_organization_role_is_not_reachable_from_the_platform_screens()
    {
        $organization = Organization::factory()->create();
        $role = Role::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.roles.edit', $role))
            ->assertNotFound();
    }

    /**
     * Organization users cannot reach the platform roles.
     */
    public function test_organization_users_cannot_reach_the_platform_roles()
    {
        $organization = Organization::factory()->create();
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));
        $admin = User::factory()->create(['organization_id' => $organization->id, 'role_id' => $role->id]);

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    /**
     * A platform role the Super Administrator created is copied into each new organization.
     */
    public function test_a_new_organization_receives_a_copy_of_every_platform_role()
    {
        $custom = Role::withoutGlobalScopes()->create([
            'organization_id' => null, 'name' => 'Lecteur', 'slug' => 'lecteur', 'is_system' => true,
        ]);
        $custom->permissions()->sync(Permission::where('slug', 'alumni.view')->pluck('id'));

        $organization = Organization::factory()->create();
        $roles = (new RoleProvisioner)->provision($organization);

        $this->assertArrayHasKey('lecteur', $roles);
        $this->assertArrayNotHasKey(Role::PLATFORM_SLUG, $roles);
        $this->assertSame($organization->id, $roles['lecteur']->organization_id);
        $this->assertFalse($roles['lecteur']->is_system);
        $this->assertSame(['alumni.view'], $roles['lecteur']->permissions()->pluck('slug')->all());
    }
}
