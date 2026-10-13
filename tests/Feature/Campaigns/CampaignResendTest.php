<?php

namespace Tests\Feature\Campaigns;

use App\Jobs\SendCampaignRecipient;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Sending a finished campaign again (docs/project.md F5).
 */
class CampaignResendTest extends TestCase
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

        Queue::fake();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::factory()->create(['max_campaigns' => null])->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $this->admin = $this->staffWith(Permission::pluck('slug')->all());
    }

    /**
     * A member of staff holding exactly these permissions.
     *
     * @param  list<string>|\Illuminate\Support\Collection<int, string>  $permissions
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
     * A finished campaign: two delivered, one failed.
     *
     * @return array{0: Campaign, 1: Collection<int, Alumni>}
     */
    private function finishedCampaign(string $status = 'sent'): array
    {
        $alumni = Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'subject' => 'Voeux 2026',
            'status' => $status,
            'recipients_count' => 3,
            'sent_at' => now()->subDay(),
        ]);

        foreach ($alumni as $index => $one) {
            $failed = $index === 2;

            $campaign->recipients()->attach($one->id, [
                'status' => $failed ? 'failed' : 'sent',
                'sent_at' => $failed ? null : now()->subDay(),
                'error_message' => $failed ? 'Adresse invalide.' : null,
                'read_at' => $failed ? null : now()->subHours(2),
            ]);
        }

        return [$campaign, $alumni];
    }

    /**
     * The recipient's pivot row, or null.
     *
     * @return array<string, mixed>|null
     */
    private function pivot(Campaign $campaign, Alumni $alumni): ?array
    {
        $row = DB::table('alumni_campaign')
            ->where('campaign_id', $campaign->id)
            ->where('alumni_id', $alumni->id)
            ->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * Only the failed addresses are retried.
     */
    public function test_only_the_failed_addresses_are_retried()
    {
        [$campaign, $alumni] = $this->finishedCampaign();

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'failed'])
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHas('success');

        Queue::assertPushed(SendCampaignRecipient::class, 1);
        Queue::assertPushed(
            fn (SendCampaignRecipient $job): bool => $job->alumniId === $alumni[2]->id,
        );

        $this->assertSame('pending', $this->pivot($campaign, $alumni[2])['status']);
        $this->assertNull($this->pivot($campaign, $alumni[2])['error_message']);

        $this->assertSame('sent', $this->pivot($campaign, $alumni[0])['status']);
        $this->assertNotNull($this->pivot($campaign, $alumni[0])['sent_at']);

        $this->assertSame('sending', $campaign->refresh()->status);
    }

    /**
     * Sending to everyone again requeues every recipient.
     */
    public function test_sending_to_everyone_again_requeues_every_recipient()
    {
        [$campaign, $alumni] = $this->finishedCampaign();

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'all'])
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHas('success');

        Queue::assertPushed(SendCampaignRecipient::class, 3);

        foreach ($alumni as $one) {
            $this->assertSame('pending', $this->pivot($campaign, $one)['status']);
            $this->assertNull($this->pivot($campaign, $one)['sent_at']);
            $this->assertNull($this->pivot($campaign, $one)['read_at']);
        }

        $this->assertSame('sending', $campaign->refresh()->status);
    }

    /**
     * A campaign where everything failed can be sent again.
     */
    public function test_a_campaign_where_everything_failed_can_be_sent_again()
    {
        [$campaign] = $this->finishedCampaign('failed');

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'failed'])
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHas('success');

        Queue::assertPushed(SendCampaignRecipient::class, 1);
        $this->assertSame('sending', $campaign->refresh()->status);
    }

    /**
     * Retrying when nothing failed says so and queues nothing.
     */
    public function test_retrying_when_nothing_failed_says_so_and_queues_nothing()
    {
        [$campaign, $alumni] = $this->finishedCampaign();

        DB::table('alumni_campaign')
            ->where('campaign_id', $campaign->id)
            ->update(['status' => 'sent', 'error_message' => null]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'failed'])
            ->assertSessionHas('error');

        Queue::assertNothingPushed();

        $this->assertSame('sent', $campaign->refresh()->status);
        $this->assertSame('sent', $this->pivot($campaign, $alumni[0])['status']);
    }

    /**
     * A draft is not resent.
     */
    public function test_a_draft_is_not_resent()
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'all'])
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertSame('draft', $campaign->refresh()->status);
    }

    /**
     * A campaign still sending is not resent.
     */
    public function test_a_campaign_still_sending_is_not_resent()
    {
        [$campaign] = $this->finishedCampaign();
        $campaign->update(['status' => 'sending']);

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'all'])
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    /**
     * Resending needs the send permission.
     */
    public function test_resending_needs_the_send_permission()
    {
        [$campaign, $alumni] = $this->finishedCampaign();

        $author = $this->staffWith(['dashboard.view', 'campaigns.view', 'campaigns.create']);

        $this->actingAs($author)
            ->post(route('campaigns.resend', $campaign), ['scope' => 'all'])
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame('sent', $this->pivot($campaign, $alumni[0])['status']);
    }

    /**
     * Another organizations campaign cannot be resent.
     */
    public function test_another_organizations_campaign_cannot_be_resent()
    {
        $other = Organization::factory()->create();

        $theirs = Campaign::factory()->create([
            'organization_id' => $other->id,
            'status' => 'sent',
            'recipients_count' => 5,
        ]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.resend', $theirs), ['scope' => 'all'])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
