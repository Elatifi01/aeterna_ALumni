<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\JobApplication;
use App\Services\EmploiMa\EmploiMaClient;
use App\Services\EmploiMa\JobQuery;
use App\Services\Jobs\JobSearch;
use App\Services\Jobs\JobSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The job board (docs/project.md F11).
 */
class JobController extends Controller
{
    private const LAST_SEARCH = 'portal.jobs.last_search';

    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly JobSearch $search,
        private readonly EmploiMaClient $emploiMa,
    ) {}

    /**
     * Show the job board with the results of the current search, or return to the last one.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $alumni = $this->alumni($request);

        $validated = $request->validate([
            'keywords' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:10'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10'],
            'reset' => ['nullable', 'boolean'],
        ]);

        $query = JobQuery::fromInput($validated);

        if ($request->boolean('reset')) {
            $request->session()->forget(self::LAST_SEARCH.'.'.$alumni->id);

            return to_route('portal.jobs.index');
        }

        if ($query->isEmpty()) {
            $last = $request->session()->get(self::LAST_SEARCH.'.'.$alumni->id);

            if (is_array($last) && $last !== []) {
                return to_route('portal.jobs.index', $last);
            }
        } else {
            $request->session()->put(self::LAST_SEARCH.'.'.$alumni->id, array_filter([
                'keywords' => $query->keywords,
                'city' => $query->city,
                'category' => $query->categoryId,
                'page' => $query->page > 1 ? $query->page : null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));
        }
        $available = $this->search->available();

        $results = $query->isEmpty()
            ? ['offers' => [], 'total' => 0, 'sources' => []]
            : $this->search->search($query);

        $known = JobApplication::query()
            ->where('alumni_id', $alumni->id)
            ->pluck('status', 'external_id');

        $offers = array_map(function (array $offer) use ($known): array {
            $offer['state'] = $known[$offer['id']] ?? null;

            return $offer;
        }, $results['offers']);

        return Inertia::render('portal/jobs/index', [
            'filters' => [
                'keywords' => $query->keywords,
                'city' => $query->city,
                'category' => $query->categoryId,
                'page' => $query->page,
            ],
            'offers' => $offers,
            'returned' => $results['total'],
            'sourceStatuses' => $results['sources'],
            'searched' => ! $query->isEmpty(),
            'available' => $available !== [],
            'sources' => array_values(array_map(
                fn (JobSource $source): array => ['key' => $source->key(), 'label' => $source->label()],
                $available,
            )),
            'categories' => isset($available['emploima']) ? $this->emploiMa->categories() : [],
        ]);
    }

    /**
     * Save an offer, or record that the alumni went to apply.
     */
    public function store(Request $request): RedirectResponse
    {
        $alumni = $this->alumni($request);

        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:200'],
            'company' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'source' => ['nullable', 'string', 'max:100'],
            'link' => ['required', 'string', 'max:2000', 'url:http,https'],
            'status' => ['required', Rule::in(['saved', 'applied'])],
        ]);

        $application = JobApplication::query()->firstOrNew([
            'alumni_id' => $alumni->id,
            'external_id' => $validated['external_id'],
        ]);

        $application->fill($validated);
        $application->alumni_id = $alumni->id;

        if ($application->status === 'applied' && $application->applied_at === null) {
            $application->applied_at = now();
        }

        if ($validated['status'] === 'saved' && $application->applied_at !== null) {
            $application->status = 'applied';
        }

        $application->save();

        return back()->with(
            'success',
            $application->status === 'applied'
                ? 'Candidature enregistree. Terminez votre postulation sur le site de l offre.'
                : 'Offre enregistree.',
        );
    }

    /**
     * Remove one of the alumni's saved offers or applications.
     */
    public function destroy(Request $request, JobApplication $application): RedirectResponse
    {
        $alumni = $this->alumni($request);

        abort_unless($application->alumni_id === $alumni->id, 404);

        $application->delete();

        return back()->with('success', 'Offre retiree de votre liste.');
    }

    /**
     * "Mes candidatures".
     */
    public function applications(Request $request): Response
    {
        $alumni = $this->alumni($request);

        return Inertia::render('portal/jobs/applications', [
            'applications' => JobApplication::query()
                ->where('alumni_id', $alumni->id)
                ->latest()
                ->paginate(20)
                ->through(fn (JobApplication $application): array => [
                    'id' => $application->id,
                    'title' => $application->title,
                    'company' => $application->company,
                    'location' => $application->location,
                    'source' => $application->source,
                    'link' => $application->link,
                    'status' => $application->status,
                    'applied_at' => $application->applied_at?->toIso8601String(),
                    'created_at' => $application->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * The alumni signed in on the portal guard.
     */
    private function alumni(Request $request): Alumni
    {
        $alumni = $request->user('alumni');

        abort_unless($alumni instanceof Alumni, 403);

        return $alumni;
    }
}
