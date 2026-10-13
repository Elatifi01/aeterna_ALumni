<?php

namespace Tests\Feature\Events;

use App\Models\Alumni;
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
 * docs/project.md F4 — event CRUD, isolation and the attendance rate.
 */
class EventCrudTest extends TestCase
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

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->unlimited()->create()->id,
            'status' => 'active',
        ]);
    }

    /**
     * The index lists only the current organization.
     */
    public function test_the_index_lists_only_the_current_organization()
    {
        Event::factory()->count(2)->create(['organization_id' => $this->organization->id]);
        Event::factory()->count(5)->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('events/index')
                ->where('events.total', 2)
            );
    }

    /**
     * An event can be created.
     */
    public function test_an_event_can_be_created()
    {
        $response = $this->actingAs($this->admin)->post(route('events.store'), [
            'title' => 'Soiree des anciens 2026',
            'starts_at' => '2026-11-20T18:00',
            'ends_at' => '2026-11-20T23:00',
            'location' => 'Casablanca',
            'capacity' => 120,
            'status' => 'published',
        ]);

        $event = Event::withoutGlobalScopes()->firstOrFail();

        $response->assertRedirect(route('events.show', $event))->assertSessionHas('success');

        $this->assertSame($this->organization->id, $event->organization_id);
        $this->assertSame($this->admin->id, $event->created_by);
        $this->assertSame(120, $event->capacity);
    }

    /**
     * The end date must not precede the start date.
     */
    public function test_the_end_date_must_not_precede_the_start_date()
    {
        $this->actingAs($this->admin)
            ->post(route('events.store'), [
                'title' => 'Evenement incoherent',
                'starts_at' => '2026-11-20T18:00',
                'ends_at' => '2026-11-19T18:00',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('ends_at');
    }

    /**
     * Capacity cannot be lowered below existing registrations.
     */
    public function test_capacity_cannot_be_lowered_below_existing_registrations()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'capacity' => 10,
        ]);

        foreach (range(1, 3) as $ignored) {
            $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
            $event->alumni()->attach($alumni->id, [
                'status' => 'registered',
                'registered_at' => now(),
                'attended' => false,
            ]);
        }

        $this->actingAs($this->admin)
            ->put(route('events.update', $event), [
                'title' => $event->title,
                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                'capacity' => 2,
                'status' => $event->status,
            ])
            ->assertSessionHasErrors('capacity');

        $this->assertSame(10, $event->refresh()->capacity);
    }

    /**
     * The attendance rate counts only active registrations.
     */
    public function test_the_attendance_rate_counts_only_active_registrations()
    {
        $event = Event::factory()->create(['organization_id' => $this->organization->id]);

        $states = [
            ['registered', true],
            ['registered', true],
            ['registered', false],
            ['registered', false],
            ['canceled', false],
        ];

        foreach ($states as [$status, $attended]) {
            $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
            $event->alumni()->attach($alumni->id, [
                'status' => $status,
                'registered_at' => now(),
                'attended' => $attended,
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('events/show')
                ->where('event.registrations_count', 4)
                ->where('event.attendees_count', 2)
                ->where('event.attendance_rate', 50)
            );
    }

    /**
     * Seats left reflects active registrations only.
     */
    public function test_seats_left_reflects_active_registrations_only()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'capacity' => 3,
        ]);

        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $event->alumni()->attach($alumni->id, [
            'status' => 'canceled',
            'registered_at' => now(),
            'attended' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('events.show', $event))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('seatsLeft', 3));
    }

    /**
     * Deleting an event is a soft delete.
     */
    public function test_deleting_an_event_is_a_soft_delete()
    {
        $event = Event::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    /**
     * Another organizations event returns 404.
     */
    public function test_another_organizations_event_returns_404()
    {
        $foreign = Event::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->actingAs($this->admin)->get(route('events.show', $foreign))->assertNotFound();
        $this->actingAs($this->admin)->get(route('events.edit', $foreign))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('events.destroy', $foreign))->assertNotFound();
    }

    /**
     * Events are blocked at the plan limit.
     */
    public function test_events_are_blocked_at_the_plan_limit()
    {
        Subscription::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->delete();

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->create(['max_events' => 1])->id,
            'status' => 'active',
        ]);

        $payload = fn (string $title) => [
            'title' => $title,
            'starts_at' => '2026-12-01T18:00',
            'status' => 'draft',
        ];

        $this->actingAs($this->admin)->post(route('events.store'), $payload('Premier'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('events.store'), $payload('Deuxieme'))
            ->assertSessionHas('error');

        $this->assertSame(1, Event::withoutGlobalScopes()->count());
    }
}
