<?php

namespace Tests\Feature\Users;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleProvisioner;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * An organization owns its roles outright.
 */
class OrganizationOwnedRolesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $admin = Role::factory()->create([
            'organization_id' => null, 'name' => 'Administrateur d organisation',
            'slug' => 'org-admin', 'is_system' => true,
        ]);
        $admin->permissions()->sync(
            Permission::whereIn('slug', Permissions::slugs())->pluck('id'),
        );

        $staff = Role::factory()->create([
            'organization_id' => null, 'name' => 'Personnel',
            'slug' => 'staff', 'is_system' => true,
        ]);
        $staff->permissions()->sync(
            Permission::whereIn('slug', Permissions::viewOnlySlugs())->pluck('id'),
        );

        $alumni = Role::factory()->create([
            'organization_id' => null, 'name' => 'Alumni',
            'slug' => 'alumni', 'is_system' => true, 'guard' => 'alumni',
        ]);
        $alumni->permissions()->sync(
            Permission::whereIn('slug', Permissions::portalSlugs())->pluck('id'),
        );
    }

    /**
     * Provision the organization's roles.
     */
    private function provision(Organization $organization): array
    {
        return app(RoleProvisioner::class)->provision($organization);
    }

    /**
     * An administrator of the organization.
     */
    private function adminOf(Organization $organization): User
    {
        $roles = $this->provision($organization);

        return User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => $roles['org-admin']->id,
        ]);
    }

    /**
     * Provisioning copies the templates permissions.
     */
    public function test_provisioning_copies_the_templates_permissions()
    {
        $organization = Organization::factory()->create();

        $roles = $this->provision($organization);

        $this->assertSame($organization->id, $roles['org-admin']->organization_id);
        $this->assertFalse((bool) $roles['org-admin']->is_system);
        $this->assertSame(
            count(Permissions::slugs()),
            $roles['org-admin']->permissions()->count(),
        );
        $this->assertSame(
            count(Permissions::viewOnlySlugs()),
            $roles['staff']->permissions()->count(),
        );
        $this->assertSame('alumni', $roles['alumni']->guard);
        $this->assertSame(
            count(Permissions::portalSlugs()),
            $roles['alumni']->permissions()->count(),
        );
    }

    /**
     * Provisioning twice does not duplicate.
     */
    public function test_provisioning_twice_does_not_duplicate()
    {
        $organization = Organization::factory()->create();

        $this->provision($organization);
        $this->provision($organization);

        $this->assertSame(
            3,
            Role::withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
        );
    }

    /**
     * An administrator can re permission their own role.
     */
    public function test_an_administrator_can_re_permission_their_own_role()
    {
        $organization = Organization::factory()->create();
        $admin = $this->adminOf($organization);
        $ownRole = $admin->role;

        $this->actingAs($admin)
            ->put(route('roles.update', $ownRole), [
                'name' => 'Direction des relations alumni',
                'permissions' => ['roles.view', 'roles.manage', 'alumni.view', 'dashboard.view'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $ownRole->refresh();
        $this->assertSame('Direction des relations alumni', $ownRole->name);
        $this->assertEqualsCanonicalizing(
            ['roles.view', 'roles.manage', 'alumni.view', 'dashboard.view'],
            $ownRole->permissions()->pluck('slug')->all(),
        );
    }

    /**
     * An administrator can re permission the staff role.
     */
    public function test_an_administrator_can_re_permission_the_staff_role()
    {
        $organization = Organization::factory()->create();
        $admin = $this->adminOf($organization);
        $staff = Role::withoutGlobalScopes()
            ->where('organization_id', $organization->id)->where('slug', 'staff')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('roles.update', $staff), [
                'name' => 'Personnel',
                'permissions' => ['alumni.view', 'alumni.create', 'events.view'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['alumni.view', 'alumni.create', 'events.view'],
            $staff->refresh()->permissions()->pluck('slug')->all(),
        );
    }

    /**
     * Editing one organizations roles leaves another untouched.
     */
    public function test_editing_one_organizations_roles_leaves_another_untouched()
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $admin = $this->adminOf($mine);
        $this->provision($theirs);

        $myStaff = Role::withoutGlobalScopes()
            ->where('organization_id', $mine->id)->where('slug', 'staff')->firstOrFail();
        $theirStaff = Role::withoutGlobalScopes()
            ->where('organization_id', $theirs->id)->where('slug', 'staff')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('roles.update', $myStaff), [
                'name' => 'Personnel restreint',
                'permissions' => ['dashboard.view'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $myStaff->refresh()->permissions()->count());
        $this->assertSame(7, $theirStaff->refresh()->permissions()->count());
        $this->assertSame('Personnel', $theirStaff->name);
    }

    /**
     * An administrator cannot strip role management from their own role.
     */
    public function test_an_administrator_cannot_strip_role_management_from_their_own_role()
    {
        $organization = Organization::factory()->create();
        $admin = $this->adminOf($organization);

        $this->actingAs($admin)
            ->put(route('roles.update', $admin->role), [
                'name' => 'Direction',
                'permissions' => ['alumni.view'],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertTrue($admin->refresh()->hasPermission('roles.manage'));
    }

    /**
     * The same slug can exist in two organizations.
     */
    public function test_the_same_slug_can_exist_in_two_organizations()
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $this->provision($mine);
        $this->provision($theirs);

        $this->assertSame(
            2,
            Role::withoutGlobalScopes()->where('slug', 'staff')->whereNotNull('organization_id')->count(),
        );
    }

    /**
     * An organization sees only its own roles.
     */
    public function test_an_organization_sees_only_its_own_roles()
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $admin = $this->adminOf($mine);
        $this->provision($theirs);

        Auth::setUser($admin);

        $this->assertSame(3, Role::count());
        $this->assertEqualsCanonicalizing(
            ['org-admin', 'staff', 'alumni'],
            Role::pluck('slug')->all(),
        );
    }

    /**
     * A new organization is provisioned with its own roles.
     */
    public function test_a_new_organization_is_provisioned_with_its_own_roles()
    {
        $superAdminRole = Role::factory()->create([
            'organization_id' => null, 'slug' => 'super-admin', 'is_system' => true, 'guard' => 'platform',
        ]);
        $superAdminRole->permissions()->sync(Permission::whereIn('slug', Permissions::platformSlugs())->pluck('id'));
        $superAdmin = User::factory()->create([
            'organization_id' => null, 'role_id' => $superAdminRole->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Institut Test',
                'email' => 'contact@it.test',
                'admin_name' => 'Responsable',
                'admin_email' => 'admin@it.test',
                'admin_password' => 'motdepasse123',
                'admin_password_confirmation' => 'motdepasse123',
            ])
            ->assertSessionHasNoErrors();

        $organization = Organization::where('name', 'Institut Test')->firstOrFail();
        $created = User::withoutGlobalScopes()->where('email', 'admin@it.test')->firstOrFail();

        $this->assertSame(
            3,
            Role::withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
        );
        $this->assertSame($organization->id, $created->role->organization_id);
        $this->assertSame('org-admin', $created->role->slug);
    }
}
