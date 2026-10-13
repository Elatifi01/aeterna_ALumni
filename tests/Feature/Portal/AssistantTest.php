<?php

namespace Tests\Feature\Portal;

use App\Models\AiConversation;
use App\Models\Alumni;
use App\Models\Event;
use App\Models\JobApplication;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\Actor;
use App\Services\AI\Tools\ToolRegistry;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The Aeterna Assistant (docs/project.md F14).
 */
class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private const OLLAMA = 'http://127.0.0.1:11434';

    private const EMPLOIMA = 'https://api.parse.bot/scraper/test-scraper';

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
            'services.ai.provider' => 'ollama',
            'services.ai.ollama.base_url' => self::OLLAMA,
            'services.ai.ollama.model' => 'test-model',
            'services.ai.max_tool_iterations' => 3,
            'services.ai.job_searches_per_conversation' => 2,
            'services.emploima.enabled' => true,
            'services.emploima.base_url' => self::EMPLOIMA,
            'services.parsebot.key' => 'test-key',
            'services.parsebot.monthly_budget' => 50,
            'services.parsebot.cache_ttl' => 3600,
        ]);

        Cache::flush();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

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
            'degree' => 'Master Informatique',
            'graduation_year' => 2021,
            'job_title' => 'Developpeuse',
            'company' => 'Atlas Digital',
            'city' => 'Casablanca',
        ]);

        $alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        return $alumni->refresh();
    }

    /**
     * Fake Ollama.
     *
     * @param  list<array<string, mixed>>  $turns
     */
    private function fakeModel(array $turns): void
    {
        $sequence = Http::sequence();

        foreach ($turns as $message) {
            $sequence->push(['message' => $message]);
        }

        Http::fake([
            self::OLLAMA.'/api/chat' => $sequence,
            self::EMPLOIMA.'/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'jobs' => [[
                        'title' => 'Developpeur Laravel - Casablanca',
                        'url' => 'https://www.emploi.ma/offre-emploi-maroc/dev-laravel-9574865',
                        'company_name' => 'Kazacube',
                        'location' => 'Casablanca-Mohammedia',
                        'description_snippet' => 'Nous cherchons un developpeur.',
                        'employment_type' => 'CDI',
                        'experience_level' => 'Experience entre 2 ans et 5 ans',
                    ]],
                    'count' => 1,
                ],
            ], 200, ['x-credits-remaining' => '187', 'x-credits-limit' => '200']),
        ]);
    }

    /**
     * One turn of prose.
     */
    private function says(string $text): array
    {
        return ['role' => 'assistant', 'content' => $text];
    }

    /**
     * One turn asking for a tool.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function asksFor(string $tool, array $arguments = []): array
    {
        return [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [['function' => ['name' => $tool, 'arguments' => $arguments]]],
        ];
    }

    /**
     * Post a question to the portal assistant.
     */
    private function ask(string $question, ?int $conversationId = null): TestResponse
    {
        return $this->actingAs($this->alumni, 'alumni')
            ->post(route('portal.assistant.store'), array_filter([
                'message' => $question,
                'conversation_id' => $conversationId,
            ]));
    }

    /**
     * The current conversation as the page shows it.
     *
     * @return list<array<string, mixed>>
     */
    private function transcript(): array
    {
        $messages = [];

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.assistant.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$messages) {
                $messages = $page->toArray()['props']['messages'];
            });

        return $messages;
    }

    /**
     * A question and its answer are kept.
     */
    public function test_a_question_and_its_answer_are_kept()
    {
        $this->fakeModel([$this->says('Bonjour.')]);

        $this->ask('Bonjour')->assertSessionHasNoErrors();

        $transcript = $this->transcript();

        $this->assertCount(2, $transcript);
        $this->assertSame('user', $transcript[0]['role']);
        $this->assertSame('Bonjour', $transcript[0]['content']);
        $this->assertSame('assistant', $transcript[1]['role']);
        $this->assertSame('Bonjour.', $transcript[1]['content']);
    }

    /**
     * A tool request is executed and fed back.
     */
    public function test_a_tool_request_is_executed_and_fed_back()
    {
        $this->fakeModel([
            $this->asksFor('get_upcoming_events'),
            $this->says('Il y a un evenement a venir.'),
        ]);

        Event::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Gala des anciens',
            'status' => 'published',
            'starts_at' => now()->addMonth(),
        ]);

        $this->ask('Quels evenements sont prevus ?');

        $transcript = $this->transcript();
        $answer = end($transcript);

        $this->assertSame('Il y a un evenement a venir.', $answer['content']);
        $this->assertSame(['get_upcoming_events'], $answer['tools_used']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/chat')
            && str_contains(json_encode($request->data()) ?: '', 'Gala des anciens'));
    }

    /**
     * The job tool returns what emploi ma returned.
     */
    public function test_the_job_tool_returns_what_emploi_ma_returned()
    {
        $this->fakeModel([
            $this->asksFor('search_jobs', ['keywords' => 'developpeur', 'city' => 'Casablanca']),
            $this->says('J ai trouve une offre.'),
        ]);

        $this->ask('Trouve-moi des offres de developpeur a Casablanca');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/chat')
            && str_contains(json_encode($request->data()) ?: '', 'Developpeur Laravel - Casablanca'));
    }

    /**
     * An alumni is never offered a staff tool.
     */
    public function test_an_alumni_is_never_offered_a_staff_tool()
    {
        $actor = $this->actorFor($this->alumni);

        $names = array_map(
            fn ($tool): string => $tool->name(),
            app(ToolRegistry::class)->for($actor),
        );

        $this->assertContains('search_jobs', $names);
        $this->assertContains('get_my_profile', $names);
        $this->assertNotContains('search_alumni', $names);
        $this->assertNotContains('get_alumni_statistics', $names);
        $this->assertNotContains('update_campaign_draft', $names);
    }

    /**
     * Naming a tool that was never offered is refused.
     */
    public function test_naming_a_tool_that_was_never_offered_is_refused()
    {
        $this->fakeModel([
            $this->asksFor('search_alumni', ['query' => 'Laravel']),
            $this->says('Je ne peux pas acceder a cette information.'),
        ]);

        $this->ask('Liste tous les alumni');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/chat')
            && str_contains(json_encode($request->data()) ?: '', 'ne vous est pas accessible'));
    }

    /**
     * A tool the model invented is refused.
     */
    public function test_a_tool_the_model_invented_is_refused()
    {
        $this->fakeModel([
            $this->asksFor('drop_all_tables'),
            $this->says('Je ne peux pas faire cela.'),
        ]);

        $this->ask('Supprime tout');

        $transcript = $this->transcript();
        $this->assertSame('Je ne peux pas faire cela.', end($transcript)['content']);
    }

    /**
     * The profile tool never leaks credentials.
     */
    public function test_the_profile_tool_never_leaks_credentials()
    {
        $this->fakeModel([
            $this->asksFor('get_my_profile'),
            $this->says('Voici votre profil.'),
        ]);

        $this->ask('Quel est mon profil ?');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/chat')) {
                return true;
            }

            $body = json_encode($request->data()) ?: '';

            return ! str_contains($body, (string) $this->alumni->password)
                && ! str_contains($body, 'login_token')
                && ! str_contains($body, 'remember_token');
        });
    }

    /**
     * A tool never reaches another organizations data.
     */
    public function test_a_tool_never_reaches_another_organizations_data()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other);

        Event::factory()->create([
            'organization_id' => $other->id,
            'title' => 'Chez les autres',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
        ]);

        $this->fakeModel([
            $this->asksFor('get_upcoming_events'),
            $this->says('Aucun evenement.'),
        ]);

        $this->ask('Quels evenements ?');

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/api/chat')
            || ! str_contains(json_encode($request->data()) ?: '', 'Chez les autres'));
    }

    /**
     * Applications are this alumnis own.
     */
    public function test_applications_are_this_alumnis_own()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        JobApplication::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'alumni_id' => $colleague->id,
            'external_id' => '999',
            'title' => 'Poste du collegue',
            'link' => 'https://www.emploi.ma/offre-emploi-maroc/x-999',
            'status' => 'saved',
        ]);

        $this->fakeModel([
            $this->asksFor('get_my_applications'),
            $this->says('Vous n avez aucune candidature.'),
        ]);

        $this->ask('Mes candidatures ?');

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/api/chat')
            || ! str_contains(json_encode($request->data()) ?: '', 'Poste du collegue'));
    }

    /**
     * One alumni cannot open another conversation.
     */
    public function test_one_alumni_cannot_open_another_conversation()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        $theirs = new AiConversation;
        $theirs->organization_id = $this->organization->id;
        $theirs->owner()->associate($colleague);
        $theirs->title = 'Privee';
        $theirs->save();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.assistant.show', $theirs))
            ->assertNotFound();

        $this->actingAs($this->alumni, 'alumni')
            ->delete(route('portal.assistant.destroy', $theirs))
            ->assertNotFound();

        $this->assertDatabaseHas('ai_conversations', ['id' => $theirs->id]);
    }

    /**
     * Posting another conversation id does not hijack it.
     */
    public function test_posting_another_conversation_id_does_not_hijack_it()
    {
        $colleague = $this->accountFor($this->organization, 'collegue@example.test');

        $theirs = new AiConversation;
        $theirs->organization_id = $this->organization->id;
        $theirs->owner()->associate($colleague);
        $theirs->title = 'Privee';
        $theirs->save();

        $this->fakeModel([$this->says('Bonjour.')]);

        $this->ask('Coucou', $theirs->id)->assertNotFound();

        $this->assertSame(0, $theirs->messages()->count());
    }

    /**
     * The job search cap holds within a conversation.
     */
    public function test_the_job_search_cap_holds_within_a_conversation()
    {
        $this->fakeModel([
            $this->asksFor('search_jobs', ['keywords' => 'developpeur']),
            $this->says('Une offre.'),
            $this->asksFor('search_jobs', ['keywords' => 'comptable']),
            $this->says('Une autre.'),
        ]);

        $this->ask('offres developpeur');
        $conversation = AiConversation::acrossOrganizations()->firstOrFail();

        $this->ask('offres comptable', $conversation->id);
        $this->ask('offres ingenieur', $conversation->id);

        $transcript = $this->transcript();
        $last = end($transcript);

        $this->assertStringContainsString('atteint le nombre de recherches', (string) $last['content']);
    }

    /**
     * The iteration cap stops a looping model.
     */
    public function test_the_iteration_cap_stops_a_looping_model()
    {
        $this->fakeModel(array_fill(0, 6, $this->asksFor('get_upcoming_events')));

        $this->ask('Quels evenements ?');

        $transcript = $this->transcript();
        $last = end($transcript);

        $this->assertSame('assistant', $last['role']);
        $this->assertStringContainsString('pas reussi a repondre', (string) $last['content']);
    }

    /**
     * Ollama being down is explained calmly.
     */
    public function test_ollama_being_down_is_explained_calmly()
    {
        Http::fake([
            self::OLLAMA.'/api/chat' => fn () => throw new ConnectionException('refused'),
        ]);

        $this->ask('Bonjour')->assertSessionHasNoErrors();

        $transcript = $this->transcript();
        $last = end($transcript);

        $this->assertStringContainsString('n est pas disponible', (string) $last['content']);
        $this->assertStringNotContainsString('Exception', (string) $last['content']);
    }

    /**
     * A missing model says which one to pull.
     */
    public function test_a_missing_model_says_which_one_to_pull()
    {
        Http::fake([self::OLLAMA.'/api/chat' => Http::response('', 404)]);

        $this->ask('Bonjour');

        $transcript = $this->transcript();

        $this->assertStringContainsString('ollama pull test-model', (string) end($transcript)['content']);
    }

    /**
     * A degraded job board is reported not invented around.
     */
    public function test_a_degraded_job_board_is_reported_not_invented_around()
    {
        Http::fake([
            self::OLLAMA.'/api/chat' => Http::sequence()
                ->push(['message' => $this->asksFor('search_jobs', ['keywords' => 'developpeur'])])
                ->push(['message' => $this->says('La recherche est indisponible.')]),
            self::EMPLOIMA.'/*' => Http::response('', 500),
        ]);

        $this->ask('Des offres ?');

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/api/chat')
            || ! str_contains(json_encode($request->data()) ?: '', 'Developpeur Laravel'));
    }

    /**
     * A guest cannot use the assistant.
     */
    public function test_a_guest_cannot_use_the_assistant()
    {
        $this->get(route('portal.assistant.index'))->assertRedirect();
        $this->post(route('portal.assistant.store'), ['message' => 'Bonjour'])->assertRedirect();

        $this->assertSame(0, AiConversation::acrossOrganizations()->count());
        Http::assertNothingSent();
    }

    /**
     * The forced password change still comes first.
     */
    public function test_the_forced_password_change_still_comes_first()
    {
        $this->alumni->forceFill(['must_change_password' => true])->save();

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.assistant.index'))
            ->assertRedirect(route('portal.password.change'));
    }

    /**
     * The organization's alumni role, with every portal permission but the ones named (docs/project.md F9).
     *
     * @param  list<string>  $without
     */
    private function alumniRole(Organization $organization, array $without = []): Role
    {
        $role = new Role(['name' => 'Alumni', 'slug' => 'alumni', 'is_system' => false, 'guard' => 'alumni']);
        $role->organization_id = $organization->id;
        $role->save();

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('slug', array_diff(Permissions::portalSlugs(), $without))
                ->pluck('id'),
        );

        return $role;
    }

    /**
     * An establishment can take the assistant out of its portal.
     */
    public function test_an_establishment_can_take_the_assistant_out_of_its_portal()
    {
        $this->alumniRole($this->organization, without: ['portal.assistant.use']);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.assistant.index'))
            ->assertForbidden();

        $this->ask('Bonjour')->assertForbidden();

        $this->assertSame(0, AiConversation::acrossOrganizations()->count());
        Http::assertNothingSent();
    }

    /**
     * The alumni role is this establishments alone.
     */
    public function test_the_alumni_role_is_this_establishments_alone()
    {
        $other = Organization::factory()->create(['slug' => 'ecole-b']);
        $this->givePlan($other);
        $this->alumniRole($other, without: ['portal.assistant.use']);

        $this->fakeModel([$this->says('Bonjour.')]);

        $this->ask('Bonjour')->assertSessionHasNoErrors();

        $this->assertCount(2, $this->transcript());
    }

    /**
     * The portal sidebar follows the alumni role.
     */
    public function test_the_portal_sidebar_follows_the_alumni_role()
    {
        $permissions = [];

        $read = function () use (&$permissions): void {
            $this->actingAs($this->alumni, 'alumni')
                ->get(route('portal.dashboard'))
                ->assertOk()
                ->assertInertia(function (AssertableInertia $page) use (&$permissions) {
                    $permissions = $page->toArray()['props']['auth']['alumni']['permissions'];
                });
        };

        $read();
        $this->assertContains('portal.assistant.use', $permissions);

        $this->alumniRole($this->organization, without: ['portal.assistant.use']);
        $this->alumni->refresh();

        $read();
        $this->assertNotContains('portal.assistant.use', $permissions);
        $this->assertContains('portal.events.view', $permissions);
    }

    /**
     * The assistant offers an alumni only what their role allows.
     */
    public function test_the_assistant_offers_an_alumni_only_what_their_role_allows()
    {
        $this->alumniRole($this->organization, without: ['portal.jobs.view', 'portal.jobs.apply']);
        $this->alumni->refresh();

        $actor = $this->actorFor($this->alumni);
        $names = array_map(fn ($tool): string => $tool->name(), app(ToolRegistry::class)->for($actor));

        $this->assertContains('get_upcoming_events', $names);
        $this->assertContains('get_my_profile', $names);
        $this->assertNotContains('search_jobs', $names);
        $this->assertNotContains('get_my_applications', $names);
    }

    /**
     * A plan without the portal closes the assistant.
     */
    public function test_a_plan_without_the_portal_closes_the_assistant()
    {
        Plan::query()->update(['alumni_portal' => false]);

        $this->actingAs($this->alumni, 'alumni')
            ->get(route('portal.assistant.index'))
            ->assertRedirect(route('portal.login', ['organization' => 'ecole-a']));

        Http::assertNothingSent();
    }

    /**
     * A staff member, for the tool-visibility checks.
     */
    private function actorFor(Alumni $alumni): Actor
    {
        $request = Request::create('/portail/assistant');
        $request->setUserResolver(fn (?string $guard = null) => $guard === 'alumni' ? $alumni : null);

        $actor = Actor::fromPortal($request);

        $this->assertNotNull($actor);

        return $actor;
    }

    /**
     * A staff user gets the staff tools not the alumni ones.
     */
    public function test_a_staff_user_gets_the_staff_tools_not_the_alumni_ones()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $request = Request::create('/dashboard');
        $request->setUserResolver(fn (?string $guard = null) => $guard === 'web' ? $user : null);

        $actor = Actor::fromStaff($request);
        $this->assertNotNull($actor);

        $names = array_map(fn ($tool): string => $tool->name(), app(ToolRegistry::class)->for($actor));

        $this->assertContains('get_upcoming_events', $names);
        $this->assertContains('search_alumni', $names);
        $this->assertContains('get_alumni_statistics', $names);
        $this->assertContains('draft_campaign', $names);
        $this->assertContains('list_campaigns', $names);
        $this->assertContains('update_campaign_draft', $names);
        $this->assertNotContains('get_my_profile', $names);
        $this->assertNotContains('get_my_applications', $names);
    }
}
