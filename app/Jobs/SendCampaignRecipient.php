<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Notifications\CampaignDelivered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one campaign e-mail to one alumni and records the outcome on the pivot (docs/project.md F5: per-recipient delivery status pending / sent / failed).
 */
class SendCampaignRecipient implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new instance.
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly int $alumniId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $campaign = Campaign::acrossOrganizations()->find($this->campaignId);
        $alumni = Alumni::acrossOrganizations()->find($this->alumniId);

        if ($campaign === null) {
            return;
        }

        if ($alumni === null) {
            $this->markRecipientById(
                $this->campaignId,
                $this->alumniId,
                'failed',
                'Alumni supprime avant l envoi.',
            );

            $this->finaliseCampaignIfDone($campaign);

            return;
        }

        if ($campaign->organization_id !== $alumni->organization_id) {
            return;
        }

        $organizationName = $campaign->organization()->withoutGlobalScopes()->value('name') ?? '';

        try {
            Mail::to($alumni->email)->send(
                new CampaignMail($campaign, $alumni, $organizationName),
            );

            $this->markRecipient($campaign, $alumni, 'sent');

            if ($alumni->has_account) {
                $alumni->notify(new CampaignDelivered($campaign));
            }
        } catch (Throwable $exception) {
            $this->markRecipient($campaign, $alumni, 'failed', $exception->getMessage());

            throw $exception;
        } finally {
            $this->finaliseCampaignIfDone($campaign);
        }
    }

    /**
     * The job exhausted its retries: record the failure so the recipient does not sit at "pending" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $campaign = Campaign::acrossOrganizations()->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $this->markRecipientById(
            $this->campaignId,
            $this->alumniId,
            'failed',
            $exception?->getMessage(),
        );

        $this->finaliseCampaignIfDone($campaign);
    }

    /**
     * Record the outcome on the recipient's pivot row.
     */
    private function markRecipient(
        Campaign $campaign,
        Alumni $alumni,
        string $status,
        ?string $error = null,
    ): void {
        $this->markRecipientById($campaign->id, $alumni->id, $status, $error);
    }

    /**
     * Record the outcome on the pivot row identified by campaign and alumni id.
     */
    private function markRecipientById(
        int $campaignId,
        int $alumniId,
        string $status,
        ?string $error = null,
    ): void {
        DB::table('alumni_campaign')
            ->where('campaign_id', $campaignId)
            ->where('alumni_id', $alumniId)
            ->update([
                'status' => $status,
                'sent_at' => $status === 'sent' ? now() : null,
                'error_message' => $error === null ? null : Str::limit($error, 250),
                'updated_at' => now(),
            ]);
    }

    /**
     * Once no recipient is still pending, close the campaign: 'sent' if at least one message got through, 'failed' if none did.
     */
    private function finaliseCampaignIfDone(Campaign $campaign): void
    {
        $counts = DB::table('alumni_campaign')
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                SUM(status = 'pending') as pending,
                SUM(status = 'sent') as sent
            ")
            ->first();

        if ($counts === null || (int) $counts->pending > 0) {
            return;
        }

        $campaign->forceFill([
            'status' => (int) $counts->sent > 0 ? 'sent' : 'failed',
            'sent_at' => now(),
        ])->save();
    }
}
