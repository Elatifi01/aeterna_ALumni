<?php

namespace Tests\Feature\Portal;

use App\Models\Alumni;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\EmploiMa\CreditBudget;
use App\Services\EmploiMa\JobQuery;
use App\Services\Jobs\JobSearch;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Searching several job sites at once (docs/project.md F11).
 */
class MultiSourceJobBoardTest extends TestCase
{
    use RefreshDatabase;

    private const EMPLOIMA = 'https://api.parse.bot/scraper/emploima-test';

    private const INDEED = 'https://api.parse.bot/scraper/indeed-test';

    private Alumni $alumni;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();

        config([
            'inertia.ssr.enabled' => false,
            'services.parsebot.key' => 'test-key',
            'services.parsebot.monthly_budget' => 10,
            'services.parsebot.cache_ttl' => 3600,
            'services.emploima.enabled' => true,
            'services.emploima.base_url' => self::EMPLOIMA,
            'services.jobs.sources.indeed' => [
                'label' => 'Indeed',
                'enabled' => true,
                'base_url' => self::INDEED,
                'endpoint' => 'search',
                'jobs_key' => 'results',
                'default_parameters' => ['l' => 'Morocco'],
                'parameters' => ['keywords' => 'q', 'city' => 'l', 'page' => 'start'],
                'fixed_parameters' => ['country' => 'ma'],
                'fields' => [
                    'title' => 'jobTitle',
                    'url' => 'jobUrl',
                    'company_name' => 'company.name',
                    'location' => 'formattedLocation',
                    'description_snippet' => 'snippet',
                    'employment_type' => 'jobType',
                ],
            ],
            'services.jobs.sources.linkedin' => [
                'label' => 'LinkedIn',
                'enabled' => false,
                'base_url' => '',
                'endpoint' => '',
            ],
        ]);

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $organization = Organization::factory()->create(['slug' => 'ecole-a']);

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::factory()->create(['alumni_portal' => true])->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $this->alumni = Alumni::factory()->create(['organization_id' => $organization->id]);
        $this->alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
        $this->alumni->refresh();
    }

    /**
     * Fake both scrapers with one offer each.
     *
     * @param  array<string, mixed>  $indeedOverrides
     */
    private function fakeBoth(array $indeedOverrides = []): void
    {
        Http::fake([
            self::EMPLOIMA.'/list_job_categories*' => Http::response([
                'status' => 'success',
                'data' => ['categories' => [['id' => '1', 'name' => 'Informatique', 'count' => 10]]],
            ], 200, ['x-credits-remaining' => '9', 'x-credits-limit' => '10']),
            self::EMPLOIMA.'/search_jobs*' => Http::response([
                'status' => 'success',
                'data' => ['jobs' => [[
                    'title' => 'Developpeur Laravel',
                    'url' => 'https://www.emploi.ma/offre-emploi-maroc/dev-laravel-111111',
                    'company_name' => 'Kazacube',
                    'location' => 'Casablanca',
                    'description_snippet' => 'Nous cherchons un developpeur.',
                ]], 'count' => 1],
            ], 200, ['x-credits-remaining' => '9', 'x-credits-limit' => '10']),
            self::INDEED.'/search*' => $indeedOverrides['response'] ?? Http::response([
                'status' => 'success',
                'data' => ['results' => [[
                    'jobTitle' => 'Ingenieur logiciel',
                    'jobUrl' => 'https://ma.indeed.com/viewjob?jk=abc123',
                    'company' => ['name' => 'Atlas Digital'],
                    'formattedLocation' => 'Casablanca',
                    'snippet' => 'Poste en CDI.',
                    'jobType' => 'CDI',
                ]]],
            ], 200, ['x-credits-remaining' => '8', 'x-credits-limit' => '10']),
        ]);
    }

    /**
     * Search the job board as the alumni.
     *
     * @param  array<string, mixed>  $extra
     */
    private function search(array $extra = []): TestResponse
    {
        return $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index', ['keywords' => 'developpeur', ...$extra]));
    }

    public function test_every_enabled_source_is_searched_and_each_offer_says_where_it_came_from()
    {
        $this->fakeBoth();

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('offers', 2)
                ->has('sources', 2)
                ->where('sources.0.key', 'emploima')
                ->where('sources.1.key', 'indeed')
                ->where('offers.0.source_label', 'emploi.ma')
                ->where('offers.1.source_label', 'Indeed')
                ->where('offers.1.title', 'Ingenieur logiciel')
                ->where('offers.1.company', 'Atlas Digital')
                ->where('sourceStatuses.indeed.degraded', false));

        Http::assertSentCount(3);
    }

    public function test_a_disabled_source_is_neither_offered_nor_called()
    {
        $this->fakeBoth();

        $this->search()->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('sourceStatuses.linkedin')
            ->where('sources', fn ($sources) => collect($sources)->pluck('key')->doesntContain('linkedin')));
    }

    public function test_the_scraper_receives_its_own_parameter_names()
    {
        $this->fakeBoth();

        $this->search(['city' => 'casablanca']);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::INDEED.'/search')
            && $request['q'] === 'developpeur'
            && $request['l'] === 'casablanca'
            && $request['country'] === 'ma'
            && $request->hasHeader('X-API-Key', 'test-key'));
    }

    public function test_a_default_location_is_sent_when_no_city_is_asked_for()
    {
        $this->fakeBoth();

        $this->search();

        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::INDEED.'/search')
            && $request['l'] === 'Morocco');
    }

    public function test_two_cities_sent_upstream_are_two_cache_entries()
    {
        $this->fakeBoth();

        $this->search(['city' => 'casablanca']);
        $this->search(['city' => 'rabat']);

        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::INDEED) && $request['l'] === 'casablanca');
        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::INDEED) && $request['l'] === 'rabat');
    }

    public function test_coming_back_to_the_board_returns_to_the_last_search_for_free()
    {
        $this->fakeBoth();

        $this->search(['city' => 'casablanca'])->assertOk();
        $spent = app(CreditBudget::class)->spentThisMonth();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index'))
            ->assertRedirect(route('portal.jobs.index', ['keywords' => 'developpeur', 'city' => 'casablanca']));

        $this->search(['city' => 'casablanca'])
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('offers', 2));

        $this->assertSame($spent, app(CreditBudget::class)->spentThisMonth());
    }

    public function test_a_new_search_link_forgets_the_last_one()
    {
        $this->fakeBoth();

        $this->search();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index', ['reset' => 1]))
            ->assertRedirect(route('portal.jobs.index'));

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('searched', false)->has('offers', 0));
    }

    public function test_the_last_search_is_the_alumnis_own()
    {
        $this->fakeBoth();

        $this->search();

        $other = Alumni::factory()->create(['organization_id' => $this->alumni->organization_id]);
        $other->forceFill(['password' => 'motdepasse123', 'must_change_password' => false, 'password_changed_at' => now()])->save();

        $this->actingAs($other->refresh(), 'alumni')
            ->get(route('portal.jobs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('searched', false));
    }

    public function test_one_site_failing_does_not_take_the_others_down()
    {
        $this->fakeBoth(['response' => Http::response('', 500)]);

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('offers', 1)
                ->where('offers.0.source', 'emploima')
                ->where('sourceStatuses.emploima.degraded', false)
                ->where('sourceStatuses.indeed.degraded', true)
                ->where('sourceStatuses.indeed.reason', 'upstream-error'));
    }

    /**
     * The sites share one credit budget: two searches plus the Metier list, then everything comes from cache.
     */
    public function test_the_sites_share_one_credit_budget()
    {
        $this->fakeBoth();

        $this->search();

        $spent = app(CreditBudget::class)->spentThisMonth();
        $this->assertSame(3, $spent);

        $this->search();

        $this->assertSame($spent, app(CreditBudget::class)->spentThisMonth());
    }

    public function test_offers_from_two_sites_are_interleaved()
    {
        $search = app(JobSearch::class);

        Http::fake([
            self::EMPLOIMA.'/*' => Http::response(['status' => 'success', 'data' => ['jobs' => [
                ['title' => 'E1', 'url' => 'https://www.emploi.ma/o-1001'],
                ['title' => 'E2', 'url' => 'https://www.emploi.ma/o-1002'],
                ['title' => 'E3', 'url' => 'https://www.emploi.ma/o-1003'],
            ], 'count' => 3]]),
            self::INDEED.'/*' => Http::response(['status' => 'success', 'data' => ['results' => [
                ['jobTitle' => 'I1', 'jobUrl' => 'https://ma.indeed.com/viewjob?jk=1'],
            ]]]),
        ]);

        $result = $search->search(JobQuery::fromInput(['keywords' => 'x']));

        $this->assertSame(['E1', 'I1', 'E2', 'E3'], array_column($result['offers'], 'title'));
    }
}
