<?php

namespace Tests\Feature\Portal;

use App\Jobs\SendCampaignRecipient;
use App\Mail\CampaignMail;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\CampaignDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The portal message inbox (docs/project.md F10).
 */
class PortalInboxTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Alumni $alumni;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->organization = Organization::factory()->create(['slug' => 'ecole-a']);
        $this->givePlan($this->organization);

        $this->alumni = $this->accountFor($this->organization, 'alumni@example.test');
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
     * A campaign already delivered to one alumni.
     */
    private function deliver(Alumni $alumni, string $subject = 'Newsletter de juin'): Campaign
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $alumni->organization_id,
            'subject' => $subject,
            'content' => "Bonjour,\n\nVoici les nouvelles.",
            'status' => 'sent',
        ]);

        $campaign->recipients()->attach($alumni->id, [
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $campaign;
    }

    /**
     * A delivered campaign appears in the inbox.
     */
    public function test_a_delivered_campaign_appears_in_the_inbox()
    {
        $this->deliver($this->alumni);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.messages.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/messages/index')
                ->has('messages.data', 1)
                ->where('messages.data.0.subject', 'Newsletter de juin')
                ->where('messages.data.0.read', false)
            );
    }

    /**
     * A campaign that was not sent is not in the inbox.
     */
    public function test_a_campaign_that_was_not_sent_is_not_in_the_inbox()
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'sending',
        ]);

        foreach (['pending', 'failed'] as $status) {
            $campaign->recipients()->syncWithoutDetaching([
                $this->alumni->id => ['status' => $status],
            ]);

            $this->actingAs($this->alumni, 'alumni')
                ->get(route('portal.messages.index'))
                ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.data', 0));

            $this->actingAs($this->alumni, 'alumni')
                ->get(route('portal.messages.show', $campaign))
                ->assertNotFound();
        }
    }

    /**
     * Opening a message marks it read.
     */
    public function test_opening_a_message_marks_it_read()
    {
        $campaign = $this->deliver($this->alumni);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.messages.show', $campaign))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/messages/show')
                ->where('message.content', "Bonjour,\n\nVoici les nouvelles.")
            );

        $this->assertDatabaseMissing('alumni_campaign', [
            'alumni_id' => $this->alumni->id,
            'campaign_id' => $campaign->id,
            'read_at' => null,
        ]);
    }

    /**
     * The unread count is shared with every portal page.
     */
    public function test_the_unread_count_is_shared_with_every_portal_page()
    {
        $this->deliver($this->alumni, 'Un');
        $this->deliver($this->alumni, 'Deux');

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.alumni.unread_messages', 2)
            );

        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.messages.read-all'))
            ->assertSessionHas('success');

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.alumni.unread_messages', 0)
            );
    }

    /**
     * An alumni cannot read another ones message.
     */
    public function test_an_alumni_cannot_read_another_ones_message()
    {
        $colleague = $this->accountFor($this->organization, 'autre@example.test');
        $campaign = $this->deliver($colleague);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.messages.show', $campaign))
            ->assertNotFound();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.messages.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.data', 0));
    }

    /**
     * An alumni cannot read another organizations message.
     */
    public function test_an_alumni_cannot_read_another_organizations_message()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other);
        $stranger = $this->accountFor($other, 'ailleurs@example.test');

        $campaign = $this->deliver($stranger, 'Chez les autres');

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.messages.show', $campaign))
            ->assertNotFound();
    }

    /**
     * Sending a campaign notifies only alumni with an account.
     */
    public function test_sending_a_campaign_notifies_only_alumni_with_an_account()
    {
        Notification::fake();

        $withAccount = $this->alumni;
        $withoutAccount = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'sans-compte@example.test',
        ]);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'sending',
        ]);

        $campaign->recipients()->attach([
            $withAccount->id => ['status' => 'pending'],
            $withoutAccount->id => ['status' => 'pending'],
        ]);

        foreach ([$withAccount, $withoutAccount] as $recipient) {
            (new SendCampaignRecipient($campaign->id, $recipient->id))->handle();
        }

        Mail::assertSent(CampaignMail::class, 2);
        Notification::assertSentTo($withAccount, CampaignDelivered::class);
        Notification::assertNotSentTo($withoutAccount, CampaignDelivered::class);
    }

    /**
     * A campaign that fails to send notifies nobody.
     */
    public function test_a_campaign_that_fails_to_send_notifies_nobody()
    {
        Notification::fake();
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'sending',
        ]);

        $campaign->recipients()->attach($this->alumni->id, ['status' => 'pending']);

        try {
            (new SendCampaignRecipient($campaign->id, $this->alumni->id))->handle();
        } catch (\Throwable) {
        }

        Notification::assertNothingSent();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.messages.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.data', 0));
    }
}
