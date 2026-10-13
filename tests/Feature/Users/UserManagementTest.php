<?php

namespace Tests\Feature\Users;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * docs/project.md F2 — internal user accounts.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Role $staffRole;

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
            'organization_id' => null, 'name' => 'Super administrateur',
            'slug' => 'super-admin', 'is_system' => true,
        ]);
        $this->organization = Organization::factory()->create();

        $this->staffRole = Role::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Personnel',
            'slug' => 'staff',
            'is_system' => false,
        ]);

        $adminRole = Role::factory()->create(['organization_id' => $this->organization->id]);
        $adminRole->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $adminRole->id,
        ]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->unlimited()->create()->id,
            'status' => 'active',
        ]);
    }

    /**
     * An account can be created and works immediately.
     */
    public function test_an_account_can_be_created_and_works_immediately()
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Nadia Chraibi',
                'email' => 'nadia@uh2.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $this->staffRole->id,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $created = User::withoutGlobalScopes()->where('email', 'nadia@uh2.test')->firstOrFail();

        $this->assertSame($this->organization->id, $created->organization_id);
        $this->assertTrue($created->is_active);
        $this->assertNotNull($created->email_verified_at);
        $this->assertTrue(Hash::check('motdepasse123', $created->password));
    }

    /**
     * The email is unique across the whole platform.
     */
    public function test_the_email_is_unique_across_the_whole_platform()
    {
        $other = Organization::factory()->create();
        $existing = User::factory()->create([
            'organization_id' => $other->id,
            'role_id' => Role::factory()->create(['organization_id' => $other->id])->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Doublon',
                'email' => $existing->email,
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $this->staffRole->id,
            ])
            ->assertSessionHasErrors('email');
    }

    /**
     * A platform role can never be assigned from a tenant.
     */
    public function test_a_platform_role_can_never_be_assigned_from_a_tenant()
    {
        $superAdminRole = Role::withoutGlobalScopes()->where('slug', 'super-admin')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Escalade',
                'email' => 'escalade@uh2.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $superAdminRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'escalade@uh2.test']);
    }

    /**
     * A user cannot deactivate themselves.
     */
    public function test_a_user_cannot_deactivate_themselves()
    {
        $this->actingAs($this->admin)
            ->patch(route('users.toggle-active', $this->admin))
            ->assertForbidden();

        $this->assertTrue($this->admin->refresh()->is_active);
    }

    /**
     * A user cannot delete themselves.
     */
    public function test_a_user_cannot_delete_themselves()
    {
        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    /**
     * A user cannot change their own role.
     */
    public function test_a_user_cannot_change_their_own_role()
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role_id' => $this->staffRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertNotSame($this->staffRole->id, $this->admin->refresh()->role_id);
    }

    /**
     * Another account can be deactivated and reactivated.
     */
    public function test_another_account_can_be_deactivated_and_reactivated()
    {
        $colleague = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $this->staffRole->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('users.toggle-active', $colleague))
            ->assertSessionHasNoErrors();
        $this->assertFalse($colleague->refresh()->is_active);

        $this->actingAs($this->admin)->patch(route('users.toggle-active', $colleague));
        $this->assertTrue($colleague->refresh()->is_active);
    }

    /**
     * Leaving the password blank keeps the current one.
     */
    public function test_leaving_the_password_blank_keeps_the_current_one()
    {
        $colleague = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $this->staffRole->id,
        ]);
        $before = $colleague->password;

        $this->actingAs($this->admin)
            ->put(route('users.update', $colleague), [
                'name' => 'Nouveau nom',
                'email' => $colleague->email,
                'role_id' => $this->staffRole->id,
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $colleague->refresh();
        $this->assertSame('Nouveau nom', $colleague->name);
        $this->assertSame($before, $colleague->password);
    }

    /**
     * A user of another organization returns 404.
     */
    public function test_a_user_of_another_organization_returns_404()
    {
        $other = Organization::factory()->create();
        $foreign = User::factory()->create([
            'organization_id' => $other->id,
            'role_id' => Role::factory()->create(['organization_id' => $other->id])->id,
        ]);

        $this->actingAs($this->admin)->get(route('users.edit', $foreign))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('users.destroy', $foreign))->assertNotFound();
    }

    /**
     * Creation is blocked at the plan limit.
     */
    public function test_creation_is_blocked_at_the_plan_limit()
    {
        Subscription::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)->delete();

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->create(['max_users' => 1])->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'De trop',
                'email' => 'detrop@uh2.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $this->staffRole->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'detrop@uh2.test']);
    }

    /**
     * Managing users requires the permission.
     */
    public function test_managing_users_requires_the_permission()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::where('slug', 'users.view')->pluck('id'));

        $viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($viewer)->get(route('users.create'))->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('users.store'), [
                'name' => 'Refuse',
                'email' => 'refuse@uh2.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $this->staffRole->id,
            ])
            ->assertForbidden();
    }
}
