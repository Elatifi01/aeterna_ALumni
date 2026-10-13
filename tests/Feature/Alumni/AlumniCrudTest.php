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
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * docs/project.md F3 — alumni management.
 */
class AlumniCrudTest extends TestCase
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
        $this->admin = $this->userWith($this->organization, Permissions::slugs());

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
    private function userWith(Organization $organization, array $permissions): User
    {
        $role = Role::factory()->create(['organization_id' => $organization->id]);
        $role->permissions()->sync(Permission::whereIn('slug', $permissions)->pluck('id'));

        return User::factory()->create([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * The index lists only the current organization.
     */
    public function test_the_index_lists_only_the_current_organization()
    {
        Alumni::factory()->count(3)->create(['organization_id' => $this->organization->id]);
        Alumni::factory()->count(7)->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->get(route('alumni.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('alumni/index')
                ->where('alumni.total', 3)
            );
    }

    /**
     * An alumni can be created.
     */
    public function test_an_alumni_can_be_created()
    {
        $response = $this->actingAs($this->admin)->post(route('alumni.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Benali',
            'email' => 'amina.benali@example.test',
            'graduation_year' => 2019,
            'company' => 'Atlas Consulting',
        ]);

        $alumni = Alumni::withoutGlobalScopes()->firstOrFail();

        $response->assertRedirect(route('alumni.show', $alumni))
            ->assertSessionHas('success');

        $this->assertSame($this->organization->id, $alumni->organization_id);
        $this->assertSame('Amina', $alumni->first_name);
    }

    /**
     * The email is unique within the organization only.
     */
    public function test_the_email_is_unique_within_the_organization_only()
    {
        $other = Organization::factory()->create();
        Alumni::factory()->create([
            'organization_id' => $other->id,
            'email' => 'shared@example.test',
        ]);

        $this->actingAs($this->admin)
            ->post(route('alumni.store'), [
                'first_name' => 'Karim',
                'last_name' => 'Idrissi',
                'email' => 'shared@example.test',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('alumni.store'), [
                'first_name' => 'Autre',
                'last_name' => 'Personne',
                'email' => 'shared@example.test',
            ])
            ->assertSessionHasErrors('email');
    }

    /**
     * An alumni can be updated.
     */
    public function test_an_alumni_can_be_updated()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->put(route('alumni.update', $alumni), [
                'first_name' => 'Nouveau',
                'last_name' => 'Nom',
                'email' => $alumni->email,
            ])
            ->assertRedirect(route('alumni.show', $alumni))
            ->assertSessionHas('success');

        $this->assertSame('Nouveau', $alumni->refresh()->first_name);
    }

    /**
     * Deleting an alumni is a soft delete.
     */
    public function test_deleting_an_alumni_is_a_soft_delete()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->delete(route('alumni.destroy', $alumni))
            ->assertRedirect(route('alumni.index'));

        $this->assertSoftDeleted('alumni', ['id' => $alumni->id]);
    }

    /**
     * Search matches name email and company.
     */
    public function test_search_matches_name_email_and_company()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Yasmine', 'last_name' => 'Alaoui',
            'email' => 'yasmine@example.test', 'company' => 'Atlas Consulting',
        ]);
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Omar', 'last_name' => 'Tazi',
            'email' => 'omar@example.test', 'company' => 'Meridien SA',
        ]);

        foreach (['Alaoui', 'yasmine@', 'Atlas'] as $term) {
            $this->actingAs($this->admin)
                ->get(route('alumni.index', ['search' => $term]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('alumni.total', 1)
                    ->where('alumni.data.0.first_name', 'Yasmine')
                );
        }
    }

    /**
     * Results can be filtered and sorted.
     */
    public function test_results_can_be_filtered_and_sorted()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Zidane', 'graduation_year' => 2020,
        ]);
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Amrani', 'graduation_year' => 2015,
        ]);

        $this->actingAs($this->admin)
            ->get(route('alumni.index', ['graduation_year' => 2020]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('alumni.total', 1));

        $this->actingAs($this->admin)
            ->get(route('alumni.index', ['sort' => 'last_name', 'direction' => 'desc']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alumni.data.0.last_name', 'Zidane')
            );
    }

    /**
     * An unknown sort column is rejected.
     */
    public function test_an_unknown_sort_column_is_rejected()
    {
        $this->actingAs($this->admin)
            ->get(route('alumni.index', ['sort' => 'password']))
            ->assertSessionHasErrors('sort');
    }
}
