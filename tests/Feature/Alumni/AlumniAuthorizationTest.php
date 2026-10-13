<?php

namespace Tests\Feature\Alumni;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/project.md §5.4 and §4 — permissions and cross-tenant access.
 */
class AlumniAuthorizationTest extends TestCase
{
    use RefreshDatabase;

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

        $this->organization = Organization::factory()->create();

        $plan = Plan::factory()->unlimited()->create();
        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    /**
     * A user holding exactly these permissions.
     *
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, ?Organization $organization = null): User
    {
        $organization ??= $this->organization;

        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->sync(Permission::whereIn('slug', $permissions)->pluck('id'));

        return User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * A user without view permission cannot open the list.
     */
    public function test_a_user_without_view_permission_cannot_open_the_list()
    {
        $user = $this->userWith(['dashboard.view']);

        $this->actingAs($user)
            ->get(route('alumni.index'))
            ->assertForbidden();
    }

    /**
     * A view only user cannot create.
     */
    public function test_a_view_only_user_cannot_create()
    {
        $user = $this->userWith(['alumni.view']);

        $this->actingAs($user)->get(route('alumni.create'))->assertForbidden();

        $this->actingAs($user)->post(route('alumni.store'), [
            'first_name' => 'Test',
            'last_name' => 'Utilisateur',
            'email' => 'test@example.test',
        ])->assertForbidden();

        $this->assertSame(0, Alumni::withoutGlobalScopes()->count());
    }

    /**
     * A view only user cannot update or delete.
     */
    public function test_a_view_only_user_cannot_update_or_delete()
    {
        $user = $this->userWith(['alumni.view']);
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $originalName = $alumni->first_name;

        $this->actingAs($user)
            ->put(route('alumni.update', $alumni), [
                'first_name' => 'Pirate',
                'last_name' => 'Nom',
                'email' => $alumni->email,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('alumni.destroy', $alumni))
            ->assertForbidden();

        $this->assertSame($originalName, $alumni->refresh()->first_name);
        $this->assertNotSoftDeleted('alumni', ['id' => $alumni->id]);
    }

    /**
     * Another organizations alumni returns 404 not 403.
     */
    public function test_another_organizations_alumni_returns_404_not_403()
    {
        $other = Organization::factory()->create();
        $foreign = Alumni::factory()->create(['organization_id' => $other->id]);

        $user = $this->userWith(Permissions::slugs());

        $this->actingAs($user)->get(route('alumni.show', $foreign))->assertNotFound();
        $this->actingAs($user)->get(route('alumni.edit', $foreign))->assertNotFound();
        $this->actingAs($user)->delete(route('alumni.destroy', $foreign))->assertNotFound();

        $this->assertNotSoftDeleted('alumni', ['id' => $foreign->id]);
    }

    /**
     * The super administrator is redirected away from the tenant space.
     */
    public function test_the_super_administrator_is_redirected_away_from_the_tenant_space()
    {
        $role = Role::factory()->create(['organization_id' => null, 'slug' => 'super-admin']);
        $superAdmin = User::factory()->create(['organization_id' => null, 'role_id' => $role->id]);

        $this->actingAs($superAdmin)
            ->get(route('alumni.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * Guests are sent to the login screen.
     */
    public function test_guests_are_sent_to_the_login_screen()
    {
        $this->get(route('alumni.index'))->assertRedirect(route('login'));
    }
}
