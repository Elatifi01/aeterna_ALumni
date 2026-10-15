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
use App\Notifications\EventPublished;
use App\Notifications\RegistrationConfirmed;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Notifications in the portal (docs/project.md F10).
 */
class PortalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Alumni $alumni;

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

        $this->organization = Organization::factory()->create(['slug' => 'ecole-a']);
        $this->givePlan($this->organization);

        $this->alumni = $this->accountFor($this->organization, 'alumni@example.test');

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Subscribe the organization to a plan with the portal.
     */
    private function givePlan(Organization $organization): void
    {
        $plan = Plan::factory()->create(['alumni_portal' => true]);

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);
    }

    /**
     * An alumni of the organization with a portal account.
     */
    private function accountFor(Organization $organization, string $email): Alumni
    {
        $alumni = Alumni::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
        ]);

        $alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        return $alumni->refresh();
    }

    /**
     * Post a new event as the administrator.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createEvent(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('events.store'), array_merge([
            'title' => 'Gala des anciens',
            'starts_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'published',
        ], $overrides));
    }

    /**
     * Publishing an event notifies the organizations alumni.
     */
    public function test_publishing_an_event_notifies_the_organizations_alumni()
    {
        Notification::fake();

        $this->createEvent()->assertSessionHasNoErrors();

        Notification::assertSentTo($this->alumni, EventPublished::class);
    }

    /**
     * A draft event notifies nobody.
     */
    public function test_a_draft_event_notifies_nobody()
    {
        Notification::fake();

        $this->createEvent(['status' => 'draft'])->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    /**
     * Only the crossing into published notifies.
     */
    public function test_only_the_crossing_into_published_notifies()
    {
        Notification::fake();

        $this->createEvent(['status' => 'draft']);
        $event = Event::acrossOrganizations()->firstOrFail();

        $this->actingAs($this->admin)->put(route('events.update', $event), [
            'title' => 'Gala des anciens',
            'starts_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($this->alumni, EventPublished::class, 1);

        $this->actingAs($this->admin)->put(route('events.update', $event), [
            'title' => 'Gala des anciens 2026',
            'starts_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($this->alumni, EventPublished::class, 1);
    }

    /**
     * A past event is not news.
     */
    public function test_a_past_event_is_not_news()
    {
        Notification::fake();

        $this->createEvent(['starts_at' => now()->subMonth()->format('Y-m-d H:i:s')]);

        Notification::assertNothingSent();
    }

    /**
     * Alumni without an account are not notified.
     */
    public function test_alumni_without_an_account_are_not_notified()
    {
        Notification::fake();

        $withoutAccount = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'sans-compte@example.test',
        ]);

        $this->createEvent();

        Notification::assertSentTo($this->alumni, EventPublished::class);
        Notification::assertNotSentTo($withoutAccount, EventPublished::class);
    }

    /**
     * Another organizations alumni are never notified.
     */
    public function test_another_organizations_alumni_are_never_notified()
    {
        Notification::fake();

        $other = Organization::factory()->create();
        $this->givePlan($other);
        $stranger = $this->accountFor($other, 'ailleurs@example.test');

        $this->createEvent();

        Notification::assertSentTo($this->alumni, EventPublished::class);
        Notification::assertNotSentTo($stranger, EventPublished::class);
    }

    /**
     * Registering confirms to the alumni however it happened.
     */
    public function test_registering_confirms_to_the_alumni_however_it_happened()
    {
        Notification::fake();

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('events.registrations.store', $event), [
                'alumni_id' => $this->alumni->id,
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($this->alumni, RegistrationConfirmed::class);
    }

    /**
     * Registering from the portal confirms too.
     */
    public function test_registering_from_the_portal_confirms_too()
    {
        Notification::fake();

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.events.register', $event))
            ->assertSessionHas('success');

        Notification::assertSentTo($this->alumni, RegistrationConfirmed::class);
    }

    /**
     * The notification list shows only this alumnis own.
     */
    public function test_the_notification_list_shows_only_this_alumnis_own()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $this->alumni->notify(new EventPublished($event));
        $colleague->notify(new EventPublished($event));

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/notifications/index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.kind', 'event')
                ->where('notifications.data.0.read', false)
            );
    }

    /**
     * An alumni cannot mark another ones notification read.
     */
    public function test_an_alumni_cannot_mark_another_ones_notification_read()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $colleague->notify(new EventPublished($event));
        $theirs = $colleague->notifications()->firstOrFail();

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.notifications.read', $theirs->id))
            ->assertNotFound();

        $this->assertNull($theirs->refresh()->read_at);
    }

    /**
     * The unread count falls to zero when all are read.
     */
    public function test_the_unread_count_falls_to_zero_when_all_are_read()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $this->alumni->notify(new EventPublished($event));
        $this->alumni->notify(new RegistrationConfirmed($event));

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.alumni.unread_notifications', 2)
            );

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.notifications.read-all'))
            ->assertSessionHas('success');

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.alumni.unread_notifications', 0)
            );
    }

    /**
     * Marking one read leaves the others alone.
     */
    public function test_marking_one_read_leaves_the_others_alone()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $this->alumni->notify(new EventPublished($event));
        $this->alumni->notify(new RegistrationConfirmed($event));

        $first = $this->alumni->notifications()->latest()->firstOrFail();

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.notifications.read', $first->id))
            ->assertRedirect();

        $this->assertNotNull($first->refresh()->read_at);
        $this->assertSame(1, $this->alumni->unreadNotifications()->count());
    }

    /**
     * The header bell receives this alumni's latest notifications, newest first.
     */
    public function test_the_header_bell_receives_the_latest_notifications()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        foreach (range(1, 7) as $ignored) {
            $this->alumni->notify(new EventPublished($event));
        }
        $colleague->notify(new RegistrationConfirmed($event));

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.alumni.recent_notifications', 5)
                ->where('auth.alumni.recent_notifications.0.kind', 'event')
                ->where('auth.alumni.recent_notifications.0.read', false)
                ->where('auth.alumni.recent_notifications.0.event_id', $event->id)
            );
    }

    /**
     * Without the notifications permission, the bell receives nothing.
     */
    public function test_the_header_bell_is_empty_without_the_permission()
    {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);
        $this->alumni->notify(new EventPublished($event));

        $role = Role::factory()->create([
            'organization_id' => $this->organization->id, 'name' => 'Alumni',
            'slug' => Permissions::ALUMNI_ROLE_SLUG, 'guard' => 'alumni',
        ]);
        $role->permissions()->sync(Permission::where('slug', 'portal.events.view')->pluck('id'));
        $this->alumni->refresh();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.alumni.recent_notifications', 0)
            );
    }
}
