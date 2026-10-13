<?php

namespace Tests\Feature\Portal;

use App\Models\Alumni;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RoleProvisioner;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The organization's ALUMNI ROLE (docs/project.md F9, §5.4).
 */
class AlumniRoleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Role $alumniRole;

    private Alumni $alumni;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['inertia.ssr.enabled' => false]);

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        foreach ([
            ['org-admin', 'Administrateur d organisation', 'web', Permissions::slugs()],
            ['staff', 'Personnel', 'web', Permissions::viewOnlySlugs()],
            ['alumni', 'Alumni', 'alumni', Permissions::portalSlugs()],
        ] as [$slug, $name, $guard, $slugs]) {
            $template = Role::factory()->create([
                'organization_id' => null, 'slug' => $slug, 'name' => $name, 'is_system' => true, 'guard' => $guard,
            ]);
            $template->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));
        }

        $this->organization = Organization::factory()->create(['slug' => 'ecole-a']);

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->create(['alumni_portal' => true])->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $roles = app(RoleProvisioner::class)->provision($this->organization);
        $this->alumniRole = $roles['alumni'];

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $roles['org-admin']->id,
        ]);

        $this->alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $this->alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
        $this->alumni->refresh();
    }

    /**
     * Remove these permissions from the alumni role.
     */
    private function revoke(string ...$slugs): void
    {
        $this->alumniRole->permissions()->detach(
            Permission::whereIn('slug', $slugs)->pluck('id'),
        );
        $this->alumni->refresh();
    }

    /**
     * Every organization gets one holding the whole portal.
     */
    public function test_every_organization_gets_one_holding_the_whole_portal()
    {
        $this->assertSame('alumni', $this->alumniRole->guard);
        $this->assertSame($this->organization->id, $this->alumniRole->organization_id);
        $this->assertEqualsCanonicalizing(
            Permissions::portalSlugs(),
            $this->alumniRole->permissions()->pluck('slug')->all(),
        );
    }

    /**
     * It is listed beside the staff roles.
     */
    public function test_it_is_listed_beside_the_staff_roles()
    {
        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('roles', 3)
                ->where('roles.0.guard', 'web')
                ->has('roles', fn (AssertableInertia $roles) => $roles
                    ->where('0.name', 'Administrateur d organisation')
                    ->where('1.name', 'Alumni')
                    ->where('1.guard', 'alumni')
                    ->where('1.users_count', 1)
                    ->where('2.name', 'Personnel')));
    }

    /**
     * Its form shows the portal catalogue and nothing else.
     */
    public function test_its_form_shows_the_portal_catalogue_and_nothing_else()
    {
        $this->actingAs($this->admin)
            ->get(route('roles.edit', $this->alumniRole))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('role.guard', 'alumni')
                ->has('catalogue', 1)
                ->where('catalogue.0.module', 'portal')
                ->has('catalogue.0.permissions', count(Permissions::portalSlugs())));
    }

    /**
     * A staff role form shows no portal permissions.
     */
    public function test_a_staff_role_form_shows_no_portal_permissions()
    {
        $staffRole = Role::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('slug', 'staff')
            ->firstOrFail();

        $modules = [];

        $this->actingAs($this->admin)
            ->get(route('roles.edit', $staffRole))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$modules) {
                $modules = array_column($page->toArray()['props']['catalogue'], 'module');
            });

        $this->assertNotContains('portal', $modules);
    }

    /**
     * An administrator can take a screen out of the portal.
     */
    public function test_an_administrator_can_take_a_screen_out_of_the_portal()
    {
        $kept = array_values(array_diff(Permissions::portalSlugs(), ['portal.jobs.view', 'portal.jobs.apply']));

        $this->actingAs($this->admin)
            ->put(route('roles.update', $this->alumniRole), [
                'name' => 'Alumni',
                'permissions' => $kept,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing($kept, $this->alumniRole->permissions()->pluck('slug')->all());
    }

    /**
     * A staff permission is refused on the alumni role.
     */
    public function test_a_staff_permission_is_refused_on_the_alumni_role()
    {
        $this->actingAs($this->admin)
            ->put(route('roles.update', $this->alumniRole), [
                'name' => 'Alumni',
                'permissions' => ['portal.events.view', 'alumni.delete'],
            ])
            ->assertSessionHasErrors('permissions.1');

        $this->alumniRole->permissions()->attach(Permission::where('slug', 'alumni.delete')->value('id'));
        $this->assertFalse($this->alumni->refresh()->hasPermission('alumni.delete'));
    }

    /**
     * It cannot be deleted.
     */
    public function test_it_cannot_be_deleted()
    {
        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $this->alumniRole))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $this->alumniRole->id, 'deleted_at' => null]);
    }

    /**
     * It cannot be given to a member of staff.
     */
    public function test_it_cannot_be_given_to_a_member_of_staff()
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Quelqu un',
                'email' => 'quelquun@example.test',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'role_id' => $this->alumniRole->id,
            ])
            ->assertSessionHasErrors('role_id');
    }

    /**
     * A revoked screen answers 403 in the portal.
     */
    public function test_a_revoked_screen_answers_403_in_the_portal()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->alumni, 'alumni')->get(route('portal.events.index'))->assertOk();

        $this->revoke('portal.events.view');

        $this->actingAs($this->alumni, 'alumni')->get(route('portal.events.index'))->assertForbidden();
        $this->actingAs($this->alumni, 'alumni')->get(route('portal.events.show', $event))->assertForbidden();

        $this->actingAs($this->alumni, 'alumni')->get(route('portal.dashboard'))->assertOk();
    }

    /**
     * Looking and acting are separate permissions.
     */
    public function test_looking_and_acting_are_separate_permissions()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addWeek(),
        ]);

        $this->revoke('portal.events.register');

        $this->actingAs($this->alumni, 'alumni')->get(route('portal.events.show', $event))->assertOk();

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.events.register', $event))
            ->assertForbidden();

        $this->assertSame(0, $event->alumni()->count());
    }

    /**
     * The password screens stay reachable whatever the role says.
     */
    public function test_the_password_screens_stay_reachable_whatever_the_role_says()
    {
        $this->alumniRole->permissions()->detach();
        $this->alumni->refresh();

        $this->actingAs($this->alumni, 'alumni')->get(route('portal.settings.password'))->assertOk();
        $this->actingAs($this->alumni, 'alumni')->get(route('portal.dashboard'))->assertOk();
    }

    /**
     * The sidebar is told what the role allows.
     */
    public function test_the_sidebar_is_told_what_the_role_allows()
    {
        $this->revoke('portal.messages.view');

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.alumni.permissions', fn ($permissions) => collect($permissions)->doesntContain('portal.messages.view')
                    && collect($permissions)->contains('portal.events.view')));
    }

    /**
     * An organization never given a role keeps the whole portal.
     */
    public function test_an_organization_never_given_a_role_keeps_the_whole_portal()
    {
        $this->alumniRole->forceDelete();
        $this->alumni->refresh();

        $this->assertEqualsCanonicalizing(Permissions::portalSlugs(), $this->alumni->portalPermissions());
        $this->actingAs($this->alumni, 'alumni')->get(route('portal.events.index'))->assertOk();
    }

    /**
     * One establishments role does not reach anothers alumni.
     */
    public function test_one_establishments_role_does_not_reach_anothers_alumni()
    {
        $other = Organization::factory()->create(['slug' => 'ecole-b']);
        $otherRoles = app(RoleProvisioner::class)->provision($other);
        $otherRoles['alumni']->permissions()->detach();

        $this->assertContains('portal.events.view', $this->alumni->refresh()->portalPermissions());
    }
}
