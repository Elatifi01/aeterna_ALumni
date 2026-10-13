<?php

namespace Tests\Feature\Portal;

use App\Models\Alumni;
use App\Models\JobApplication;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\EmploiMa\CreditBudget;
use App\Services\EmploiMa\EmploiMaClient;
use App\Services\EmploiMa\JobQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The emploi.ma job board (docs/project.md F11).
 */
class JobBoardTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.parse.bot/scraper/test-scraper';

    private Organization $organization;

    private Alumni $alumni;

    /**
     * Prepare the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'inertia.ssr.enabled' => false,
            'services.emploima.enabled' => true,
            'services.emploima.base_url' => self::BASE,
            'services.parsebot.key' => 'test-key',
            'services.parsebot.monthly_budget' => 5,
            'services.parsebot.cache_ttl' => 3600,
        ]);

        Cache::flush();

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
     * The real envelope emploi.ma returns, verified against the live API: {"status":"success","data":{"jobs":[…],"count":25}}.
     *
     * @param  list<array<string, mixed>>  $jobs
     */
    private function fakeSearch(array $jobs = [], int $status = 200): void
    {
        Http::fake([
            self::BASE.'/search_jobs*' => Http::response([
                'status' => 'success',
                'data' => ['jobs' => $jobs, 'count' => count($jobs)],
            ], $status, ['x-credits-remaining' => '187', 'x-credits-limit' => '200']),
            self::BASE.'/list_job_categories*' => Http::response([
                'status' => 'success',
                'data' => ['categories' => [
                    ['name' => 'Informatique, nouvelles technologies', 'id' => '31', 'count' => 63],
                    ['name' => 'Achats', 'id' => '1127', 'count' => 33],
                ]],
            ], 200, ['x-credits-remaining' => '186', 'x-credits-limit' => '200']),
        ]);
    }

    /**
     * One offer in emploi.ma's real shape: no id of its own, and a location that lists several regions.
     *
     * @return array<string, mixed>
     */
    private function offer(
        string $slug = 'developpeur-senior-odoo-python-casablanca-9574865',
        string $title = 'Developpeur Senior Odoo | Python - Casablanca',
        string $location = 'Casablanca-Mohammedia',
    ): array {
        return [
            'title' => $title,
            'url' => "https://www.emploi.ma/offre-emploi-maroc/{$slug}",
            'company_name' => 'Atlas Digital',
            'company_url' => 'https://www.emploi.ma/recruteur/9006460',
            'description_snippet' => 'Nous cherchons un <b>developpeur</b>  confirme.',
            'experience_level' => 'Experience entre 5 ans et 10 ans',
            'employment_type' => 'CDI',
            'location' => $location,
        ];
    }

    /**
     * Search the job board as the alumni.
     *
     * @param  array<string, mixed>  $params
     */
    private function search(array $params = []): TestResponse
    {
        return $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index', $params + ['keywords' => 'developpeur']));
    }

    /**
     * A search returns offers.
     */
    public function test_a_search_returns_offers()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/jobs/index')
                ->has('offers', 1)
                ->where('offers.0.title', 'Developpeur Senior Odoo | Python - Casablanca')
                ->where('offers.0.company', 'Atlas Digital')
                ->where('offers.0.employment_type', 'CDI')
                ->where('offers.0.snippet', 'Nous cherchons un developpeur confirme.')
                ->where('sourceStatuses.emploima.degraded', false)
            );
    }

    /**
     * The offer id comes from the url since emploi ma sends none.
     */
    public function test_the_offer_id_comes_from_the_url_since_emploi_ma_sends_none()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('offers.0.id', '9574865')
            );
    }

    /**
     * An url without a numeric id still gets a stable one.
     */
    public function test_an_url_without_a_numeric_id_still_gets_a_stable_one()
    {
        $this->fakeSearch([$this->offer(slug: 'une-offre-sans-numero')]);

        $id = null;

        $this->search()->assertInertia(function (AssertableInertia $page) use (&$id) {
            $id = $page->toArray()['props']['offers'][0]['id'];
        });

        $this->assertIsString($id);
        $this->assertSame(32, strlen((string) $id));
    }

    /**
     * The same search spends one credit not two.
     */
    public function test_the_same_search_spends_one_credit_not_two()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()->assertOk();
        $this->search()->assertOk();

        Http::assertSentCount(2);
        $this->assertSame(2, app(CreditBudget::class)->spentThisMonth());
    }

    /**
     * Spelling variations of one search are one credit.
     */
    public function test_spelling_variations_of_one_search_are_one_credit()
    {
        $this->fakeSearch([$this->offer()]);

        foreach (['developpeur web', 'Développeur  Web', '  DEVELOPPEUR WEB '] as $keywords) {
            $this->actingAs($this->alumni, 'alumni')
                ->get(route('portal.jobs.index', ['keywords' => $keywords]))
                ->assertOk();
        }

        Http::assertSentCount(2);
    }

    /**
     * Changing only the city costs nothing extra.
     */
    public function test_changing_only_the_city_costs_nothing_extra()
    {
        $this->fakeSearch([
            $this->offer(location: 'Casablanca-Mohammedia'),
            $this->offer(slug: 'comptable-rabat-9000001', title: 'Comptable - Rabat', location: 'Rabat-Sale-Kenitra'),
        ]);

        $this->search()->assertOk();

        $this->search(['city' => 'Rabat'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('offers', 1)
                ->where('offers.0.title', 'Comptable - Rabat')
                ->where('returned', 2)
            );

        Http::assertSentCount(2);
    }

    /**
     * The city filter ignores accents and case.
     */
    public function test_the_city_filter_ignores_accents_and_case()
    {
        $this->fakeSearch([$this->offer(location: 'Béni Mellal-Khénifra - Casablanca-Mohammedia')]);

        $this->search(['city' => 'beni mellal'])
            ->assertInertia(fn (AssertableInertia $page) => $page->has('offers', 1));
    }

    /**
     * A city that matches nothing says so without blaming the search.
     */
    public function test_a_city_that_matches_nothing_says_so_without_blaming_the_search()
    {
        $this->fakeSearch([$this->offer(location: 'Casablanca-Mohammedia')]);

        $this->search(['city' => 'Dakhla'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('offers', 0)
                ->where('returned', 1)
            );
    }

    /**
     * A category alone is a valid search.
     */
    public function test_a_category_alone_is_a_valid_search()
    {
        $this->fakeSearch([$this->offer()]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index', ['category' => '31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('searched', true)
                ->has('offers', 1)
            );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'metier_id=31'));
    }

    /**
     * The category list is offered to the screen.
     */
    public function test_the_category_list_is_offered_to_the_screen()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('categories', 2)
                ->where('categories.0.id', '31')
                ->where('categories.0.name', 'Informatique, nouvelles technologies')
                ->where('categories.0.count', 63)
            );
    }

    /**
     * The categories are cached across visits.
     */
    public function test_the_categories_are_cached_across_visits()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()->assertOk();
        $this->search()->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'list_job_categories'));
        Http::assertSentCount(2);
    }

    /**
     * An empty search never calls emploi ma.
     */
    public function test_an_empty_search_never_calls_emploi_ma()
    {
        $this->fakeSearch([$this->offer()]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('searched', false)
                ->has('offers', 0)
            );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'list_job_categories'));
        Http::assertSentCount(1);
    }

    /**
     * The api key travels in a header and never in the url.
     */
    public function test_the_api_key_travels_in_a_header_and_never_in_the_url()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()->assertOk();

        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'test-key')
            && ! str_contains($request->url(), 'test-key'));
    }

    /**
     * The key never appears in the response.
     */
    public function test_the_key_never_appears_in_the_response()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()->assertOk()->assertDontSee('test-key');
    }

    /**
     * An exhausted budget serves the cache and says so.
     */
    public function test_an_exhausted_budget_serves_the_cache_and_says_so()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()->assertOk();

        $budget = app(CreditBudget::class);
        while ($budget->trySpend()) {
        }

        $this->travel(2)->hours();

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('offers', 1)
                ->where('sourceStatuses.emploima.degraded', true)
                ->where('sourceStatuses.emploima.reason', 'budget-exhausted')
            );
    }

    /**
     * A rate limit is reported as temporary.
     */
    public function test_a_rate_limit_is_reported_as_temporary()
    {
        Http::fake([
            self::BASE.'/search_jobs*' => Http::response('', 429),
            self::BASE.'/list_job_categories*' => Http::response([
                'status' => 'success', 'data' => ['categories' => []],
            ], 200),
        ]);

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sourceStatuses.emploima.degraded', true)
                ->where('sourceStatuses.emploima.reason', 'rate-limited')
            );
    }

    /**
     * An upstream error does not reach the alumni.
     */
    public function test_an_upstream_error_does_not_reach_the_alumni()
    {
        $this->fakeSearch([], 500);

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sourceStatuses.emploima.degraded', true)
                ->where('sourceStatuses.emploima.reason', 'upstream-error')
                ->has('offers', 0)
            );
    }

    /**
     * A body that is not a success envelope is refused.
     */
    public function test_a_body_that_is_not_a_success_envelope_is_refused()
    {
        Http::fake([
            self::BASE.'/*' => Http::response(['status' => 'error', 'message' => 'nope'], 200),
        ]);

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sourceStatuses.emploima.degraded', true)
                ->where('sourceStatuses.emploima.reason', 'upstream-error')
            );
    }

    /**
     * A missing key disables the board without spending anything.
     */
    public function test_a_missing_key_disables_the_board_without_spending_anything()
    {
        config(['services.parsebot.key' => null]);

        $this->search()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('available', false)
                ->where('sources', [])
                ->where('sourceStatuses', [])
            );

        Http::assertNothingSent();
        $this->assertSame(0, app(CreditBudget::class)->spentThisMonth());
    }

    /**
     * A malformed row is skipped rather than fatal.
     */
    public function test_a_malformed_row_is_skipped_rather_than_fatal()
    {
        $this->fakeSearch([
            ['title' => 'Sans lien'],
            ['url' => 'https://www.emploi.ma/offre-emploi-maroc/sans-titre-1'],
            $this->offer(),
            ['nonsense' => true],
        ]);

        $this->search()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('offers', 1)
                ->where('offers.0.id', '9574865')
            );
    }

    /**
     * The remaining credits reported by emploi ma are recorded.
     */
    public function test_the_remaining_credits_reported_by_emploi_ma_are_recorded()
    {
        $this->fakeSearch([$this->offer()]);

        $this->search()->assertOk();

        $summary = app(CreditBudget::class)->summary();

        $this->assertSame(200, $summary['limit']);
        $this->assertIsInt($summary['remaining']);
        $this->assertNotNull($summary['reported_at']);
    }

    /**
     * Paging is capped so a crawler cannot drain the month.
     */
    public function test_paging_is_capped_so_a_crawler_cannot_drain_the_month()
    {
        $query = JobQuery::fromInput(['keywords' => 'php', 'page' => 500]);

        $this->assertSame(10, $query->page);
    }

    /**
     * The budget refuses once the month is spent.
     */
    public function test_the_budget_refuses_once_the_month_is_spent()
    {
        $budget = app(CreditBudget::class);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($budget->trySpend());
        }

        $this->assertFalse($budget->trySpend());
        $this->assertSame(5, $budget->spentThisMonth());

        $this->assertFalse($budget->trySpend());
        $this->assertSame(5, $budget->spentThisMonth());
    }

    /**
     * A degraded search with nothing cached returns empty rather than failing.
     */
    public function test_a_degraded_search_with_nothing_cached_returns_empty_rather_than_failing()
    {
        config(['services.parsebot.monthly_budget' => 0]);

        $result = app(EmploiMaClient::class)->search(
            JobQuery::fromInput(['keywords' => 'jamais demande']),
        );

        $this->assertTrue($result['degraded']);
        $this->assertSame([], $result['offers']);
        Http::assertNothingSent();
    }

    /**
     * An alumni can save an offer and see it in their applications.
     */
    public function test_an_alumni_can_save_an_offer_and_see_it_in_their_applications()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.jobs.store'), [
                'external_id' => '9574865',
                'title' => 'Developpeur Senior Odoo',
                'company' => 'Atlas Digital',
                'location' => 'Casablanca-Mohammedia',
                'source' => 'emploi.ma',
                'link' => 'https://www.emploi.ma/offre-emploi-maroc/developpeur-9574865',
                'status' => 'saved',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('job_applications', [
            'alumni_id' => $this->alumni->id,
            'organization_id' => $this->organization->id,
            'external_id' => '9574865',
            'status' => 'saved',
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.applications'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/jobs/applications')
                ->has('applications.data', 1)
            );
    }

    /**
     * Applying records when it happened.
     */
    public function test_applying_records_when_it_happened()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.jobs.store'), [
                'external_id' => '9574865',
                'title' => 'Developpeur Senior Odoo',
                'link' => 'https://www.emploi.ma/offre-emploi-maroc/developpeur-9574865',
                'status' => 'applied',
            ])
            ->assertSessionHas('success');

        $application = JobApplication::acrossOrganizations()->firstOrFail();

        $this->assertSame('applied', $application->status);
        $this->assertNotNull($application->applied_at);
    }

    /**
     * Saving again does not demote an application.
     */
    public function test_saving_again_does_not_demote_an_application()
    {
        foreach (['applied', 'saved'] as $status) {
            $this->actingAs($this->alumni, 'alumni')->post(route('portal.jobs.store'), [
                'external_id' => '9574865',
                'title' => 'Developpeur Senior Odoo',
                'link' => 'https://www.emploi.ma/offre-emploi-maroc/developpeur-9574865',
                'status' => $status,
            ]);
        }

        $application = JobApplication::acrossOrganizations()->firstOrFail();

        $this->assertSame('applied', $application->status);
        $this->assertSame(1, JobApplication::acrossOrganizations()->count());
    }

    /**
     * A javascript link is refused.
     */
    public function test_a_javascript_link_is_refused()
    {
        $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.jobs.store'), [
                'external_id' => '1',
                'title' => 'Piege',
                'link' => 'javascript:alert(1)',
                'status' => 'saved',
            ])
            ->assertSessionHasErrors('link');

        $this->assertSame(0, JobApplication::acrossOrganizations()->count());
    }

    /**
     * An alumni only ever sees their own applications.
     */
    public function test_an_alumni_only_ever_sees_their_own_applications()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        $theirs = JobApplication::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'alumni_id' => $colleague->id,
            'external_id' => '9000009',
            'title' => 'Chez le collegue',
            'link' => 'https://www.emploi.ma/offre-emploi-maroc/x-9000009',
            'status' => 'saved',
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.applications'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('applications.data', 0));

        $this->actingAs($this->alumni, 'alumni')
            ->delete(route('portal.jobs.destroy', $theirs))
            ->assertNotFound();

        $this->assertDatabaseHas('job_applications', ['id' => $theirs->id]);
    }

    /**
     * An alumni cannot touch another organizations application.
     */
    public function test_an_alumni_cannot_touch_another_organizations_application()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other);
        $stranger = $this->accountFor($other, 'ailleurs@example.test');

        $theirs = JobApplication::query()->forceCreate([
            'organization_id' => $other->id,
            'alumni_id' => $stranger->id,
            'external_id' => '9000009',
            'title' => 'Ailleurs',
            'link' => 'https://www.emploi.ma/offre-emploi-maroc/x-9000009',
            'status' => 'saved',
        ]);

        $this->actingAs($this->alumni, 'alumni')
            ->delete(route('portal.jobs.destroy', $theirs))
            ->assertNotFound();

        $this->assertDatabaseHas('job_applications', ['id' => $theirs->id]);
    }

    /**
     * The board is closed to a plan without the portal.
     */
    public function test_the_board_is_closed_to_a_plan_without_the_portal()
    {
        Plan::query()->update(['alumni_portal' => false]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.jobs.index', ['keywords' => 'developpeur']))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        Http::assertNothingSent();
    }
}
