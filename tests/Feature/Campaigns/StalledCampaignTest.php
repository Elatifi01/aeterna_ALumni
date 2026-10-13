<?php

namespace Tests\Feature\Campaigns;

use App\Models\Alumni;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A campaign left mid-send with nothing consuming the queue (docs/project.md F5).
 */
class StalledCampaignTest extends TestCase
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
    }

    /**
     * A campaign mid-send, whose last activity was $minutesAgo minutes ago.
     */
    private function sendingCampaign(int $minutesAgo, string $recipientStatus = 'pending'): Campaign
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'sending',
            'recipients_count' => 1,
        ]);

        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $campaign->recipients()->attach($alumni->id, ['status' => $recipientStatus]);

        $stamp = now()->subMinutes($minutesAgo);

        DB::table('alumni_campaign')->where('campaign_id', $campaign->id)->update(['updated_at' => $stamp]);
        DB::table('campaigns')->where('id', $campaign->id)->update(['updated_at' => $stamp]);

        return $campaign->refresh();
    }

    /**
     * The stalled flag the campaign page reports.
     */
    private function stalledFlag(Campaign $campaign): bool
    {
        $value = null;

        $this->actingAs($this->admin)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$value) {
                $value = $page->toArray()['props']['stalled'];
            });

        return (bool) $value;
    }

    /**
     * A send that has not moved is reported as stalled.
     */
    public function test_a_send_that_has_not_moved_is_reported_as_stalled()
    {
        $this->assertTrue($this->stalledFlag($this->sendingCampaign(10)));
    }

    /**
     * A send that only just started is not yet stalled.
     */
    public function test_a_send_that_only_just_started_is_not_yet_stalled()
    {
        $this->assertFalse($this->stalledFlag($this->sendingCampaign(0)));
    }

    /**
     * A campaign with nothing pending is never stalled.
     */
    public function test_a_campaign_with_nothing_pending_is_never_stalled()
    {
        $this->assertFalse($this->stalledFlag($this->sendingCampaign(10, 'sent')));
    }

    /**
     * A draft is never stalled.
     */
    public function test_a_draft_is_never_stalled()
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->stalledFlag($campaign));
    }

    /**
     * A sent campaign is never stalled.
     */
    public function test_a_sent_campaign_is_never_stalled()
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->stalledFlag($campaign));
    }

    /**
     * The flag clears once the queue moves again.
     */
    public function test_the_flag_clears_once_the_queue_moves_again()
    {
        $campaign = $this->sendingCampaign(10);

        $this->assertTrue($this->stalledFlag($campaign));

        DB::table('alumni_campaign')
            ->where('campaign_id', $campaign->id)
            ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);

        $this->assertFalse($this->stalledFlag($campaign));
    }
}
