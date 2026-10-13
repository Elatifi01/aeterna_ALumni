<?php

namespace Tests\Feature\Tenancy;

use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Deleting the rows ticked in a table (docs/project.md §10).
 */
class DeleteSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Organization $other;

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
        $this->other = Organization::factory()->create();
        $this->admin = $this->staffWith(Permission::pluck('slug')->all());
    }

    /**
     * A member of staff holding exactly these permissions.
     *
     * @param  iterable<int, string>  $permissions
     */
    private function staffWith(iterable $permissions): User
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', collect($permissions)->all())->pluck('id')
        );

        return User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * The three tables a selection can be deleted from.
     *
     * @return array<string, array{class-string, string, string, string}>
     */
    public static function tables(): array
    {
        return [
            'alumni' => [Alumni::class, 'alumni', 'alumni.destroy-selection', 'alumni.delete'],
            'events' => [Event::class, 'events', 'events.destroy-selection', 'events.delete'],
            'campaigns' => [Campaign::class, 'campaigns', 'campaigns.destroy-selection', 'campaigns.create'],
        ];
    }

    /**
     * The ticked rows go to the bin together.
     *
     * @param  class-string<Model>  $model
     */
    #[DataProvider('tables')]
    public function test_the_ticked_rows_go_to_the_bin_together(string $model, string $table, string $route, string $permission): void
    {
        $mine = $model::factory()->count(3)->create(['organization_id' => $this->organization->id]);
        $kept = $model::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->delete(route($route), ['ids' => $mine->pluck('id')->all()])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_starts_with($message, '3 '));

        foreach ($mine as $row) {
            $this->assertSoftDeleted($table, ['id' => $row->id]);
        }

        $this->assertNotSoftDeleted($table, ['id' => $kept->id]);
    }

    /**
     * Another establishments rows are not touched.
     *
     * @param  class-string<Model>  $model
     */
    #[DataProvider('tables')]
    public function test_another_establishments_rows_are_not_touched(string $model, string $table, string $route, string $permission): void
    {
        $mine = $model::factory()->create(['organization_id' => $this->organization->id]);
        $theirs = $model::factory()->count(2)->create(['organization_id' => $this->other->id]);

        $this->actingAs($this->admin)
            ->delete(route($route), ['ids' => [$mine->id, ...$theirs->pluck('id')->all()]])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_starts_with($message, '1 '));

        $this->assertSoftDeleted($table, ['id' => $mine->id]);

        foreach ($theirs as $row) {
            $this->assertNotSoftDeleted($table, ['id' => $row->id]);
        }
    }

    /**
     * It needs the same permission as deleting one.
     *
     * @param  class-string<Model>  $model
     */
    #[DataProvider('tables')]
    public function test_it_needs_the_same_permission_as_deleting_one(string $model, string $table, string $route, string $permission): void
    {
        $rows = $model::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $everythingBut = Permission::query()->where('slug', '!=', $permission)->pluck('slug');
        $limited = $this->staffWith($everythingBut);

        $this->actingAs($limited)
            ->delete(route($route), ['ids' => $rows->pluck('id')->all()])
            ->assertForbidden();

        foreach ($rows as $row) {
            $this->assertNotSoftDeleted($table, ['id' => $row->id]);
        }
    }

    /**
     * An empty selection is refused.
     */
    public function test_an_empty_selection_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('alumni.destroy-selection'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    /**
     * A selection of nothing that exists says so.
     */
    public function test_a_selection_of_nothing_that_exists_says_so(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('alumni.destroy-selection'), ['ids' => [999998, 999999]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Aucune ligne de la selection n a pu etre supprimee.');
    }

    /**
     * A sent campaign can be part of the selection.
     */
    public function test_a_sent_campaign_can_be_part_of_the_selection(): void
    {
        $sent = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);
        $draft = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('campaigns.destroy-selection'), ['ids' => [$sent->id, $draft->id]])
            ->assertSessionHas('success', '2 campagnes supprimees.');

        $this->assertSoftDeleted('campaigns', ['id' => $sent->id]);
        $this->assertSoftDeleted('campaigns', ['id' => $draft->id]);
    }
}
