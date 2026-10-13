<?php

namespace Tests\Feature\Campaigns;

use App\Jobs\SendCampaignRecipient;
use App\Mail\CampaignMail;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * docs/project.md F5 — queued sending and per-recipient delivery status.
 */
class CampaignSendingTest extends TestCase
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
     * Create alumni in the organization.
     */
    private function alumni(int $count): void
    {
        Alumni::factory()->count($count)->create(['organization_id' => $this->organization->id]);
    }

    /**
     * A draft campaign addressed to these alumni.
     */
    private function draft(array $overrides = []): Campaign
    {
        $this->actingAs($this->admin)
            ->post(route('campaigns.store'), array_merge([
                'subject' => 'Invitation a la soiree des anciens',
                'content' => 'Bonjour, nous vous invitons a notre soiree annuelle.',
                'recipients' => 'all',
            ], $overrides))
            ->assertSessionHasNoErrors();

        return Campaign::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /**
     * Saving a campaign materialises its recipients as pending.
     */
    public function test_saving_a_campaign_materialises_its_recipients_as_pending()
    {
        $this->alumni(4);

        $campaign = $this->draft();

        $this->assertSame('draft', $campaign->status);
        $this->assertSame(4, $campaign->recipients_count);
        $this->assertSame(4, $campaign->recipients()->count());
        $this->assertSame(
            4,
            $campaign->recipients()->wherePivot('status', 'pending')->count(),
        );
    }

    /**
     * Filtered recipients use the alumni filters.
     */
    public function test_filtered_recipients_use_the_alumni_filters()
    {
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2020,
        ]);
        Alumni::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2015,
        ]);

        $campaign = $this->draft([
            'recipients' => 'filtered',
            'graduation_year' => 2020,
        ]);

        $this->assertSame(3, $campaign->recipients_count);
    }

    /**
     * All ignores a leftover filter.
     */
    public function test_all_ignores_a_leftover_filter()
    {
        $this->alumni(6);

        $campaign = $this->draft([
            'recipients' => 'all',
            'graduation_year' => 1999,
        ]);

        $this->assertSame(6, $campaign->recipients_count);
    }

    /**
     * A selection matching nobody is refused.
     */
    public function test_a_selection_matching_nobody_is_refused()
    {
        $this->alumni(3);

        $this->actingAs($this->admin)
            ->post(route('campaigns.store'), [
                'subject' => 'Personne',
                'content' => 'Contenu',
                'recipients' => 'filtered',
                'graduation_year' => 1951,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Campaign::withoutGlobalScopes()->count());
    }

    /**
     * Sending queues one job per recipient.
     */
    public function test_sending_queues_one_job_per_recipient()
    {
        Queue::fake();
        $this->alumni(3);

        $campaign = $this->draft();

        $this->actingAs($this->admin)
            ->post(route('campaigns.send', $campaign))
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHas('success');

        Queue::assertPushed(SendCampaignRecipient::class, 3);
        $this->assertSame('sending', $campaign->refresh()->status);
    }

    /**
     * A campaign cannot be sent twice.
     */
    public function test_a_campaign_cannot_be_sent_twice()
    {
        Queue::fake();
        $this->alumni(2);

        $campaign = $this->draft();

        $this->actingAs($this->admin)->post(route('campaigns.send', $campaign));
        Queue::assertPushed(SendCampaignRecipient::class, 2);

        $this->actingAs($this->admin)
            ->post(route('campaigns.send', $campaign))
            ->assertSessionHas('error');

        Queue::assertPushed(SendCampaignRecipient::class, 2);
    }

    /**
     * The job sends the mail and marks the recipient sent.
     */
    public function test_the_job_sends_the_mail_and_marks_the_recipient_sent()
    {
        Mail::fake();
        $this->alumni(1);

        $campaign = $this->draft();
        $alumni = Alumni::withoutGlobalScopes()->firstOrFail();

        $campaign->update(['status' => 'sending']);
        (new SendCampaignRecipient($campaign->id, $alumni->id))->handle();

        Mail::assertSent(CampaignMail::class, fn ($mail) => $mail->hasTo($alumni->email));

        $this->assertDatabaseHas('alumni_campaign', [
            'campaign_id' => $campaign->id,
            'alumni_id' => $alumni->id,
            'status' => 'sent',
        ]);

        $this->assertSame('sent', $campaign->refresh()->status);
    }

    /**
     * A failing recipient is recorded and does not stop the others.
     */
    public function test_a_failing_recipient_is_recorded_and_does_not_stop_the_others()
    {
        $this->alumni(2);
        $campaign = $this->draft();

        [$first, $second] = Alumni::withoutGlobalScopes()->orderBy('id')->get()->all();
        $campaign->update(['status' => 'sending']);

        Mail::fake();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP refuse'));

        try {
            (new SendCampaignRecipient($campaign->id, $first->id))->handle();
        } catch (\Throwable) {
        }

        $this->assertDatabaseHas('alumni_campaign', [
            'campaign_id' => $campaign->id,
            'alumni_id' => $first->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('alumni_campaign', [
            'campaign_id' => $campaign->id,
            'alumni_id' => $second->id,
            'status' => 'pending',
        ]);

        $this->assertSame('sending', $campaign->refresh()->status);
    }

    /**
     * The job refuses to cross organizations.
     */
    public function test_the_job_refuses_to_cross_organizations()
    {
        Mail::fake();
        $this->alumni(1);
        $campaign = $this->draft();

        $foreign = Alumni::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        (new SendCampaignRecipient($campaign->id, $foreign->id))->handle();

        Mail::assertNothingSent();
    }

    /**
     * Sending requires the send permission.
     */
    public function test_sending_requires_the_send_permission()
    {
        Queue::fake();
        $this->alumni(1);
        $campaign = $this->draft();

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['campaigns.view', 'campaigns.create'])->pluck('id'),
        );

        $author = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($author)
            ->post(route('campaigns.send', $campaign))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    /**
     * A campaign with no recipients is refused and stays a draft.
     */
    public function test_a_campaign_with_no_recipients_is_refused_and_stays_a_draft()
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'Sans destinataires',
            'status' => 'draft',
            'recipients_count' => 0,
        ]);

        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('campaigns.send', $campaign))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertSame('draft', $campaign->refresh()->status);
    }

    /**
     * Sending without the permission is still a 403.
     */
    public function test_sending_without_the_permission_is_still_a_403()
    {
        Queue::fake();
        $this->alumni(1);
        $campaign = $this->draft();

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['campaigns.view'])->pluck('id'),
        );

        $viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($viewer)
            ->post(route('campaigns.send', $campaign))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }
}
