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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * docs/project.md F4 — registrations, capacity and attendance.
 */
class EventRegistrationTest extends TestCase
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
     * An event in the organization.
     */
    private function event(?int $capacity = null): Event
    {
        return Event::factory()->create([
            'organization_id' => $this->organization->id,
            'capacity' => $capacity,
        ]);
    }

    /**
     * An alumni in the organization.
     */
    private function alumni(?Organization $organization = null): Alumni
    {
        return Alumni::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
        ]);
    }

    /**
     * Register the alumni for the event as the administrator.
     */
    private function register(Event $event, Alumni $alumni): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('events.registrations.store', $event), ['alumni_id' => $alumni->id]);
    }

    /**
     * An alumni can be registered.
     */
    public function test_an_alumni_can_be_registered()
    {
        $event = $this->event();
        $alumni = $this->alumni();

        $this->register($event, $alumni)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('alumni_event', [
            'event_id' => $event->id,
            'alumni_id' => $alumni->id,
            'status' => 'registered',
            'attended' => false,
        ]);
    }

    /**
     * The same alumni cannot be registered twice.
     */
    public function test_the_same_alumni_cannot_be_registered_twice()
    {
        $event = $this->event();
        $alumni = $this->alumni();

        $this->register($event, $alumni)->assertSessionHasNoErrors();
        $this->register($event, $alumni)->assertSessionHasErrors('alumni_id');

        $this->assertSame(1, $event->alumni()->count());
    }

    /**
     * Capacity is enforced.
     */
    public function test_capacity_is_enforced()
    {
        $event = $this->event(capacity: 2);

        $this->register($event, $this->alumni())->assertSessionHasNoErrors();
        $this->register($event, $this->alumni())->assertSessionHasNoErrors();
        $this->register($event, $this->alumni())->assertSessionHasErrors('alumni_id');

        $this->assertSame(2, $event->registrations()->count());
    }

    /**
     * A null capacity is unlimited.
     */
    public function test_a_null_capacity_is_unlimited()
    {
        $event = $this->event(capacity: null);

        foreach (range(1, 5) as $ignored) {
            $this->register($event, $this->alumni())->assertSessionHasNoErrors();
        }

        $this->assertSame(5, $event->registrations()->count());
    }

    /**
     * Cancelling frees a seat and keeps the row.
     */
    public function test_cancelling_frees_a_seat_and_keeps_the_row()
    {
        $event = $this->event(capacity: 1);
        $first = $this->alumni();

        $this->register($event, $first);
        $this->register($event, $this->alumni())->assertSessionHasErrors('alumni_id');

        $this->actingAs($this->admin)
            ->delete(route('events.registrations.destroy', [$event, $first]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('alumni_event', [
            'event_id' => $event->id,
            'alumni_id' => $first->id,
            'status' => 'canceled',
        ]);

        $this->register($event, $this->alumni())->assertSessionHasNoErrors();
    }

    /**
     * A cancelled registration is reinstated not duplicated.
     */
    public function test_a_cancelled_registration_is_reinstated_not_duplicated()
    {
        $event = $this->event();
        $alumni = $this->alumni();

        $this->register($event, $alumni);
        $this->actingAs($this->admin)->delete(route('events.registrations.destroy', [$event, $alumni]));
        $this->register($event, $alumni)->assertSessionHasNoErrors();

        $this->assertSame(1, $event->alumni()->count());
        $this->assertDatabaseHas('alumni_event', [
            'event_id' => $event->id,
            'alumni_id' => $alumni->id,
            'status' => 'registered',
        ]);
    }

    /**
     * An alumni of another organization cannot be registered.
     */
    public function test_an_alumni_of_another_organization_cannot_be_registered()
    {
        $event = $this->event();
        $foreign = $this->alumni(Organization::factory()->create());

        $this->register($event, $foreign)->assertSessionHasErrors('alumni_id');

        $this->assertDatabaseMissing('alumni_event', [
            'event_id' => $event->id,
            'alumni_id' => $foreign->id,
        ]);
    }

    /**
     * Attendance can be marked and unmarked.
     */
    public function test_attendance_can_be_marked_and_unmarked()
    {
        $event = $this->event();
        $alumni = $this->alumni();
        $this->register($event, $alumni);

        $this->actingAs($this->admin)
            ->patch(route('events.registrations.attendance', [$event, $alumni]), ['attended' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $event->alumni()->first()->pivot->attended);

        $this->actingAs($this->admin)
            ->patch(route('events.registrations.attendance', [$event, $alumni]), ['attended' => false]);

        $pivot = $event->alumni()->first()->pivot;
        $this->assertFalse((bool) $pivot->attended);
        $this->assertNull($pivot->attended_at);
    }

    /**
     * Attendance cannot be marked for a cancelled registration.
     */
    public function test_attendance_cannot_be_marked_for_a_cancelled_registration()
    {
        $event = $this->event();
        $alumni = $this->alumni();
        $this->register($event, $alumni);
        $this->actingAs($this->admin)->delete(route('events.registrations.destroy', [$event, $alumni]));

        $this->actingAs($this->admin)
            ->patch(route('events.registrations.attendance', [$event, $alumni]), ['attended' => true])
            ->assertSessionHasErrors('attended');
    }

    /**
     * Registrations require the registrations permission.
     */
    public function test_registrations_require_the_registrations_permission()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['events.view', 'events.update'])->pluck('id'),
        );

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $event = $this->event();

        $this->actingAs($user)
            ->post(route('events.registrations.store', $event), ['alumni_id' => $this->alumni()->id])
            ->assertForbidden();
    }
}
