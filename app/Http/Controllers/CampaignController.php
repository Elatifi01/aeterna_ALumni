<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitReachedException;
use App\Http\Controllers\Concerns\DeletesSelection;
use App\Http\Requests\Campaign\IndexCampaignRequest;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Jobs\SendCampaignRecipient;
use App\Models\Alumni;
use App\Models\Campaign;
use App\Services\CampaignRecipientResolver;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email campaigns (docs/project.md F5).
 */
class CampaignController extends Controller
{
    use DeletesSelection;

    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly PlanLimitService $planLimits,
        private readonly CampaignRecipientResolver $recipients,
    ) {}

    /**
     * List the campaigns, filtered and paginated.
     */
    public function index(IndexCampaignRequest $request): Response
    {
        $filters = $request->safe()->only(['search', 'status', 'sort', 'direction', 'per_page']);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $campaigns = Campaign::query()
            ->withCount([
                'recipients as sent_count' => fn ($query) => $query->where('alumni_campaign.status', 'sent'),
                'recipients as failed_count' => fn ($query) => $query->where('alumni_campaign.status', 'failed'),
            ])
            ->when($filters['search'] ?? null, function ($query, string $term) {
                $query->where('subject', 'like', '%'.addcslashes($term, '%_').'%');
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'status' => $campaign->status,
                'recipients_count' => $campaign->recipients_count,
                'sent_count' => (int) $campaign->sent_count,
                'failed_count' => (int) $campaign->failed_count,
                'sent_at' => $campaign->sent_at?->toIso8601String(),
                'created_at' => $campaign->created_at?->toIso8601String(),
            ]);

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => (int) ($filters['per_page'] ?? 10),
            ],
            'usage' => $this->planLimits->usageFor($request->user()->organization, 'campaigns'),
        ]);
    }

    /**
     * Show the form for creating a campaign.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Campaign::class);

        return Inertia::render('campaigns/create', [
            'filterOptions' => Inertia::defer(fn () => $this->filterOptions()),
            'totalAlumni' => Alumni::count(),
            'recipientCount' => $this->recipients->count($this->criteriaFrom($request)),
            'criteria' => $this->criteriaFrom($request),
        ]);
    }

    /**
     * Create a campaign from the submitted form.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        try {
            $this->planLimits->ensureCanCreate($request->user()->organization, 'campaigns');
        } catch (PlanLimitReachedException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $validated = $request->validated();
        $recipientIds = $this->recipients->ids($validated);

        if ($recipientIds === []) {
            return back()->withInput()
                ->with('error', 'Aucun alumni ne correspond a cette selection de destinataires.');
        }

        $campaign = DB::transaction(function () use ($request, $validated, $recipientIds) {
            $campaign = Campaign::create([
                'created_by' => $request->user()->id,
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'status' => 'draft',
                'recipients_count' => count($recipientIds),
            ]);

            $this->syncRecipients($campaign, $recipientIds);

            return $campaign;
        });

        return to_route('campaigns.show', $campaign)->with('success', 'Campagne enregistree.');
    }

    /**
     * Show one campaign with its recipients and their delivery status.
     */
    public function show(Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        $recipients = $campaign->recipients()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Alumni $alumni) => [
                'id' => $alumni->id,
                'first_name' => $alumni->first_name,
                'last_name' => $alumni->last_name,
                'email' => $alumni->email,
                'status' => $alumni->pivot->getAttribute('status'),
                'sent_at' => $alumni->pivot->getAttribute('sent_at'),
                'error_message' => $alumni->pivot->getAttribute('error_message'),
            ]);

        return Inertia::render('campaigns/show', [
            'campaign' => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'content' => $campaign->content,
                'status' => $campaign->status,
                'recipients_count' => $campaign->recipients_count,
                'sent_at' => $campaign->sent_at?->toIso8601String(),
                'created_at' => $campaign->created_at?->toIso8601String(),
            ],
            'recipients' => $recipients,
            'summary' => [
                'pending' => $recipients->where('status', 'pending')->count(),
                'sent' => $recipients->where('status', 'sent')->count(),
                'failed' => $recipients->where('status', 'failed')->count(),
            ],
            'stalled' => $this->hasStalled($campaign),
        ]);
    }

    /**
     * Whether a campaign has been left mid-send with nothing consuming it.
     */
    private function hasStalled(Campaign $campaign): bool
    {
        if ($campaign->status !== 'sending') {
            return false;
        }

        $rows = DB::table('alumni_campaign')->where('campaign_id', $campaign->id);

        if ((clone $rows)->where('status', 'pending')->doesntExist()) {
            return false;
        }

        $lastActivity = max(
            (string) (clone $rows)->max('updated_at'),
            (string) $campaign->updated_at,
        );

        return $lastActivity !== '' && now()->diffInSeconds($lastActivity, true) > 90;
    }

    /**
     * Show the form for editing a campaign.
     */
    public function edit(Request $request, Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);

        return Inertia::render('campaigns/edit', [
            'campaign' => [
                'id' => $campaign->id,
                'subject' => $campaign->subject,
                'content' => $campaign->content,
                'recipients_count' => $campaign->recipients_count,
            ],
            'filterOptions' => Inertia::defer(fn () => $this->filterOptions()),
            'totalAlumni' => Alumni::count(),
            'recipientCount' => $this->recipients->count($this->criteriaFrom($request)),
            'criteria' => $this->criteriaFrom($request),
        ]);
    }

    /**
     * Update a campaign from the submitted form.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validated();
        $recipientIds = $this->recipients->ids($validated);

        if ($recipientIds === []) {
            return back()->withInput()
                ->with('error', 'Aucun alumni ne correspond a cette selection de destinataires.');
        }

        DB::transaction(function () use ($campaign, $validated, $recipientIds) {
            $campaign->update([
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'recipients_count' => count($recipientIds),
            ]);

            $this->syncRecipients($campaign, $recipientIds);
        });

        return to_route('campaigns.show', $campaign)->with('success', 'Campagne mise a jour.');
    }

    /**
     * Delete a campaign.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return to_route('campaigns.index')->with('success', 'Campagne supprimee.');
    }

    /**
     * Delete the campaigns ticked in the list.
     */
    public function destroySelection(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Campaign::class);

        $count = $this->deleteSelection($request, Campaign::query());

        return to_route('campaigns.index')
            ->with('success', $this->selectionDeletedMessage($count, 'campagne supprimee', 'campagnes supprimees'));
    }

    /**
     * Queue the campaign (docs/project.md F5: sending is queued).
     */
    public function send(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        $queued = DB::transaction(function () use ($campaign) {
            $fresh = Campaign::query()->lockForUpdate()->find($campaign->id);

            if ($fresh === null || $fresh->status !== 'draft') {
                return null;
            }

            $pending = DB::table('alumni_campaign')
                ->where('campaign_id', $fresh->id)
                ->where('status', 'pending')
                ->pluck('alumni_id');

            if ($pending->isNotEmpty()) {
                $fresh->update(['status' => 'sending']);
            }

            return $pending;
        });

        if ($queued === null) {
            return back()->with(
                'error',
                'Cette campagne a deja ete envoyee ou est en cours d envoi.',
            );
        }

        if ($queued->isEmpty()) {
            return back()->with(
                'error',
                'Cette campagne n a aucun destinataire en attente. Modifiez-la pour selectionner des destinataires.',
            );
        }

        foreach ($queued as $alumniId) {
            SendCampaignRecipient::dispatch($campaign->id, (int) $alumniId);
        }

        return to_route('campaigns.show', $campaign)
            ->with('success', "Campagne en cours d envoi a {$queued->count()} alumni.");
    }

    /**
     * Send a finished campaign again (docs/project.md F5).
     */
    public function resend(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'failed';

        $queued = DB::transaction(function () use ($campaign, $scope) {
            $fresh = Campaign::query()->lockForUpdate()->find($campaign->id);

            if ($fresh === null || ! in_array($fresh->status, ['sent', 'failed'], true)) {
                return null;
            }

            $rows = DB::table('alumni_campaign')->where('campaign_id', $fresh->id);

            if ($scope === 'failed') {
                $rows->where('status', 'failed');
            }

            $alumniIds = $rows->pluck('alumni_id');

            if ($alumniIds->isEmpty()) {
                return $alumniIds;
            }

            DB::table('alumni_campaign')
                ->where('campaign_id', $fresh->id)
                ->whereIn('alumni_id', $alumniIds)
                ->update([
                    'status' => 'pending',
                    'sent_at' => null,
                    'error_message' => null,
                    'read_at' => null,
                    'updated_at' => now(),
                ]);

            $fresh->update(['status' => 'sending']);

            return $alumniIds;
        });

        if ($queued === null) {
            return back()->with(
                'error',
                'Seule une campagne terminee peut etre renvoyee.',
            );
        }

        if ($queued->isEmpty()) {
            return back()->with(
                'error',
                $scope === 'failed'
                    ? 'Aucun envoi n a echoue sur cette campagne.'
                    : 'Cette campagne n a aucun destinataire.',
            );
        }

        foreach ($queued as $alumniId) {
            SendCampaignRecipient::dispatch($campaign->id, (int) $alumniId);
        }

        return to_route('campaigns.show', $campaign)->with(
            'success',
            $scope === 'failed'
                ? "Nouvel envoi en cours vers {$queued->count()} adresse(s) en echec."
                : "Nouvel envoi en cours vers {$queued->count()} alumni.",
        );
    }

    /**
     * The recipient criteria carried on the query string.
     *
     * @return array<string, mixed>
     */
    private function criteriaFrom(Request $request): array
    {
        return [
            'recipients' => $request->string('recipients')->toString() === 'filtered' ? 'filtered' : 'all',
            'search' => $request->string('search')->toString() ?: null,
            'graduation_year' => $request->integer('graduation_year') ?: null,
            'degree' => $request->string('degree')->toString() ?: null,
            'company' => $request->string('company')->toString() ?: null,
        ];
    }

    /**
     * Replace the campaign's recipients with these alumni, all pending.
     *
     * @param  array<int, int>  $recipientIds
     */
    private function syncRecipients(Campaign $campaign, array $recipientIds): void
    {
        $now = now();

        $campaign->recipients()->sync(
            collect($recipientIds)
                ->mapWithKeys(fn (int $id) => [$id => [
                    'status' => 'pending',
                    'sent_at' => null,
                    'error_message' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]])
                ->all(),
        );
    }

    /**
     * The distinct promotions, degrees and companies to filter recipients by.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'graduationYears' => Alumni::query()
                ->whereNotNull('graduation_year')->distinct()
                ->orderByDesc('graduation_year')->pluck('graduation_year'),
            'degrees' => Alumni::query()
                ->whereNotNull('degree')->distinct()->orderBy('degree')->pluck('degree'),
            'companies' => Alumni::query()
                ->whereNotNull('company')->distinct()->orderBy('company')->pluck('company'),
        ];
    }
}
