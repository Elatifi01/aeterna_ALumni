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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Deleting a campaign, whatever its status (docs/project.md F5).
 */
class CampaignDeletionTest extends TestCase
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

        Mail::fake();

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
     * @param  list<string>|Collection<int, string>  $permissions
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
     * A campaign with real recipient rows, at whatever status.
     *
     * @return array{0: Campaign, 1: \Illuminate\Database\Eloquent\Collection<int, Alumni>}
     */
    private function campaignWithRecipients(string $status, string $pivotStatus = 'sent'): array
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
            'sent_at' => $status === 'sent' ? now()->subDay() : null,
        ]);

        $campaign->recipients()->sync(
            $alumni->mapWithKeys(fn (Alumni $one) => [$one->id => [
                'status' => $pivotStatus,
                'sent_at' => $pivotStatus === 'sent' ? now()->subDay() : null,
            ]])->all(),
        );

        return [$campaign, $alumni];
    }

    /**
     * A sent campaign can be deleted.
     */
    public function test_a_sent_campaign_can_be_deleted()
    {
        [$campaign] = $this->campaignWithRecipients('sent');

        $this->actingAs($this->admin)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect(route('campaigns.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
    }

    /**
     * A draft can still be deleted.
     */
    public function test_a_draft_can_still_be_deleted()
    {
        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect(route('campaigns.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
    }

    /**
     * The delivery record survives and comes back.
     */
    public function test_the_delivery_record_survives_and_comes_back()
    {
        [$campaign] = $this->campaignWithRecipients('sent');

        $this->actingAs($this->admin)->delete(route('campaigns.destroy', $campaign));

        $this->actingAs($this->admin)
            ->patch(route('trash.restore', ['type' => 'campaigns', 'id' => $campaign->id]))
            ->assertRedirect();

        $restored = Campaign::query()->findOrFail($campaign->id);

        $this->assertSame('sent', $restored->status);
        $this->assertSame(3, $restored->recipients_count);
        $this->assertSame(3, $restored->recipients()->count());
        $this->assertSame(3, $restored->recipients()->wherePivot('status', 'sent')->count());
        $this->assertNotNull($restored->sent_at);
    }

    /**
     * Deleting mid send stops the mail that has not gone out.
     */
    public function test_deleting_mid_send_stops_the_mail_that_has_not_gone_out()
    {
        [$campaign, $alumni] = $this->campaignWithRecipients('sending', 'pending');

        $this->actingAs($this->admin)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect(route('campaigns.index'))
            ->assertSessionHas('success');

        (new SendCampaignRecipient($campaign->id, $alumni->first()->id))->handle();

        Mail::assertNothingOutgoing();
    }

    /**
     * A sent campaign still cannot be edited.
     */
    public function test_a_sent_campaign_still_cannot_be_edited()
    {
        [$campaign] = $this->campaignWithRecipients('sent');

        $this->actingAs($this->admin)
            ->get(route('campaigns.edit', $campaign))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('campaigns.update', $campaign), [
                'subject' => 'Reecrite',
                'content' => 'Contenu.',
                'recipients' => 'all',
            ])
            ->assertForbidden();

        $this->assertSame('Voeux 2026', $campaign->refresh()->subject);
    }

    /**
     * Deleting needs the campaign permission.
     */
    public function test_deleting_needs_the_campaign_permission()
    {
        [$campaign] = $this->campaignWithRecipients('sent');

        $reader = $this->staffWith(['dashboard.view', 'campaigns.view']);

        $this->actingAs($reader)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertForbidden();

        $this->assertNotSoftDeleted('campaigns', ['id' => $campaign->id]);
    }
}
