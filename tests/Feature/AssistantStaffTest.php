<?php

namespace Tests\Feature;

use App\Models\AiConversation;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Aeterna Assistant in the staff space (docs/project.md F14).
 */
class AssistantStaffTest extends TestCase
{
    use RefreshDatabase;

    private const OLLAMA = 'http://127.0.0.1:11434';

    private Organization $organization;

    private User $user;

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
        ]);

        Cache::flush();

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create();
        $this->givePlan($this->organization);
        $this->user = $this->staffWith(Permission::pluck('slug')->all());
    }

    /**
     * Subscribe the organization to a plan.
     */
    private function givePlan(Organization $organization, int $maxCampaigns = 50): Plan
    {
        $plan = Plan::factory()->create([
            'alumni_portal' => true,
            'max_campaigns' => $maxCampaigns,
        ]);

        Subscription::factory()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        return $plan;
    }

    /**
     * A member of staff holding exactly these permissions and no others.
     *
     * @param  list<string>  $permissions
     */
    private function staffWith(array $permissions): User
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', $permissions)->pluck('id'));

        return User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
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

        Http::fake([self::OLLAMA.'/api/chat' => $sequence]);
    }

    /**
     * A model turn of prose.
     *
     * @return array<string, mixed>
     */
    private function says(string $text): array
    {
        return ['role' => 'assistant', 'content' => $text];
    }

    /**
     * A model turn asking for a tool.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
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
     * Post a question to the staff assistant.
     */
    private function ask(string $question, ?User $as = null, ?int $conversationId = null): TestResponse
    {
        return $this->actingAs($as ?? $this->user)
            ->post(route('assistant.store'), array_filter([
                'message' => $question,
                'conversation_id' => $conversationId,
            ]));
    }

    /**
     * The current conversation as the page shows it.
     *
     * @return list<array<string, mixed>>
     */
    private function transcript(?User $as = null): array
    {
        $messages = [];

        $this->actingAs($as ?? $this->user)
            ->get(route('assistant.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$messages) {
                $messages = $page->toArray()['props']['messages'];
            });

        return $messages;
    }

    /**
     * What reached the model, as one string.
     */
    private function payload(mixed $request): string
    {
        return str_replace('\\"', '"', json_encode($request->data()) ?: '');
    }

    /**
     * Assert the text reached the model.
     */
    private function assertSentToModel(string $needle): void
    {
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/chat')
            && str_contains($this->payload($request), $needle));
    }

    /**
     * Assert the text never reached the model.
     */
    private function assertNeverSentToModel(string $needle): void
    {
        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/api/chat')
            || ! str_contains($this->payload($request), $needle));
    }

    /**
     * The alumni search returns real records.
     */
    public function test_the_alumni_search_returns_real_records()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Salma',
            'last_name' => 'Benali',
            'company' => 'Atlas Digital',
            'graduation_year' => 2023,
        ]);

        $this->fakeModel([
            $this->asksFor('search_alumni', ['company' => 'Atlas Digital']),
            $this->says('J ai trouve une personne.'),
        ]);

        $this->ask('Qui travaille chez Atlas Digital ?')->assertSessionHasNoErrors();

        $this->assertSentToModel('Benali');

        $transcript = $this->transcript();
        $this->assertSame(['search_alumni'], end($transcript)['tools_used']);
    }

    /**
     * The alumni search never crosses the tenant boundary.
     */
    public function test_the_alumni_search_never_crosses_the_tenant_boundary()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other);

        Alumni::factory()->create([
            'organization_id' => $other->id,
            'last_name' => 'Ailleurs',
            'company' => 'Atlas Digital',
        ]);

        $this->fakeModel([
            $this->asksFor('search_alumni', ['company' => 'Atlas Digital']),
            $this->says('Aucun alumni.'),
        ]);

        $this->ask('Qui travaille chez Atlas Digital ?');

        $this->assertNeverSentToModel('Ailleurs');
    }

    /**
     * Statistics are counted in sql not by the model.
     */
    public function test_statistics_are_counted_in_sql_not_by_the_model()
    {
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'city' => 'Casablanca',
        ]);
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'city' => 'Rabat',
        ]);

        $this->fakeModel([
            $this->asksFor('get_alumni_statistics', ['group_by' => 'city']),
            $this->says('Casablanca en compte trois.'),
        ]);

        $this->ask('Quelles villes comptent le plus d alumni ?');

        $this->assertSentToModel('"label":"Casablanca","count":3');
    }

    /**
     * Drafting without an audience asks instead of creating.
     */
    public function test_drafting_without_an_audience_asks_instead_of_creating()
    {
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2024,
        ]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Invitation au gala',
                'content' => 'Chers alumni, nous vous invitons au gala.',
            ]),
            $this->says('Qui doit recevoir cette campagne ?'),
        ]);

        $this->ask('Redige une campagne pour le gala');

        $this->assertSame(0, Campaign::query()->count());

        $this->assertSentToModel('"needs_recipients":true');
        $this->assertSentToModel('"all":3');
        $this->assertSentToModel('"value":2024,"count":3');
    }

    /**
     * Filtered with nothing to filter on still asks.
     */
    public function test_filtered_with_nothing_to_filter_on_still_asks()
    {
        Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Objet',
                'content' => 'Corps',
                'recipients' => ['mode' => 'filtered'],
            ]),
            $this->says('Qui doit la recevoir ?'),
        ]);

        $this->ask('Redige une campagne');

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSentToModel('"needs_recipients":true');
    }

    /**
     * A draft for everyone carries every alumni.
     */
    public function test_a_draft_for_everyone_carries_every_alumni()
    {
        Alumni::factory()->count(4)->create(['organization_id' => $this->organization->id]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Invitation au gala',
                'content' => 'Chers alumni, nous vous invitons au gala.',
                'recipients' => ['mode' => 'all'],
            ]),
            $this->says('J ai prepare un brouillon pour tous les alumni.'),
        ]);

        $this->ask('Redige une campagne pour le gala, pour tout le monde');

        $campaign = Campaign::query()->firstOrFail();

        $this->assertSame('Invitation au gala', $campaign->subject);
        $this->assertSame('draft', $campaign->status);
        $this->assertSame(4, $campaign->recipients_count);
        $this->assertSame(4, $campaign->recipients()->count());
        $this->assertSame($this->organization->id, $campaign->organization_id);
        $this->assertSame($this->user->id, $campaign->created_by);

        $this->assertNull($campaign->sent_at);
        $this->assertSame(4, $campaign->recipients()->wherePivot('status', 'pending')->count());
    }

    /**
     * A draft can be narrowed to one promotion.
     */
    public function test_a_draft_can_be_narrowed_to_one_promotion()
    {
        Alumni::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2024,
        ]);
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2019,
        ]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Felicitations',
                'content' => 'Bravo a la promotion 2024.',
                'recipients' => ['mode' => 'filtered', 'graduation_year' => 2024],
            ]),
            $this->says('Brouillon pret pour la promotion 2024.'),
        ]);

        $this->ask('Redige une campagne pour la promotion 2024');

        $campaign = Campaign::query()->firstOrFail();

        $this->assertSame(2, $campaign->recipients_count);
        $this->assertSame(
            [2024, 2024],
            $campaign->recipients()->pluck('graduation_year')->all(),
        );

        $this->assertSentToModel('promotion : 2024');
    }

    /**
     * A draft never reaches another establishments alumni.
     */
    public function test_a_draft_never_reaches_another_establishments_alumni()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other);

        Alumni::factory()->count(2)->create(['organization_id' => $this->organization->id]);
        Alumni::factory()->count(5)->create(['organization_id' => $other->id]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Objet',
                'content' => 'Corps',
                'recipients' => ['mode' => 'all'],
            ]),
            $this->says('Brouillon pret.'),
        ]);

        $this->ask('Redige une campagne pour tout le monde');

        $this->assertSame(2, Campaign::query()->firstOrFail()->recipients_count);
    }

    /**
     * An audience matching nobody is refused.
     */
    public function test_an_audience_matching_nobody_is_refused()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2019,
        ]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Objet',
                'content' => 'Corps',
                'recipients' => ['mode' => 'filtered', 'graduation_year' => 1998],
            ]),
            $this->says('Aucun alumni ne correspond.'),
        ]);

        $this->ask('Redige une campagne pour la promotion 1998');

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSentToModel('Aucun alumni ne correspond');
    }

    /**
     * Drafting is refused at the plan limit.
     */
    public function test_drafting_is_refused_at_the_plan_limit()
    {
        Plan::query()->update(['max_campaigns' => 1]);

        Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->fakeModel([
            $this->asksFor('draft_campaign', [
                'subject' => 'Une de trop',
                'content' => 'Contenu.',
                'recipients' => ['mode' => 'all'],
            ]),
            $this->says('Votre plan ne le permet pas.'),
        ]);

        $this->ask('Redige une campagne');

        $this->assertSame(1, Campaign::query()->count());
        $this->assertSentToModel('limite');
    }

    /**
     * THE REGRESSION THAT PROMPTED update_campaign_draft.
     */
    public function test_modifying_a_campaign_changes_it_instead_of_creating_another()
    {
        Alumni::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2024,
        ]);
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2019,
        ]);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'subject' => 'Invitation au gala',
            'status' => 'draft',
            'recipients_count' => 0,
        ]);

        $this->fakeModel([
            $this->asksFor('update_campaign_draft', [
                'campaign_id' => $campaign->id,
                'recipients' => ['mode' => 'filtered', 'graduation_year' => 2024],
            ]),
            $this->says('J ai ajoute les destinataires.'),
        ]);

        $this->ask('Modifie la campagne du gala et ajoute les destinataires');

        $this->assertSame(1, Campaign::query()->count());

        $campaign->refresh();
        $this->assertSame('Invitation au gala', $campaign->subject);
        $this->assertSame(2, $campaign->recipients_count);
        $this->assertSame(2, $campaign->recipients()->count());
        $this->assertSame('draft', $campaign->status);
        $this->assertNull($campaign->sent_at);
    }

    /**
     * Adding recipients keeps the ones already there.
     */
    public function test_adding_recipients_keeps_the_ones_already_there()
    {
        $existing = Alumni::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2024,
        ]);
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2019,
        ]);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'recipients_count' => 2,
        ]);
        $campaign->recipients()->sync($existing->pluck('id')->all());

        $this->fakeModel([
            $this->asksFor('update_campaign_draft', [
                'campaign_id' => $campaign->id,
                'recipients' => ['mode' => 'add', 'graduation_year' => 2019],
            ]),
            $this->says('Ajoutes.'),
        ]);

        $this->ask('Ajoute aussi la promotion 2019');

        $this->assertSame(5, $campaign->refresh()->recipients_count);
        $this->assertSame(5, $campaign->recipients()->count());
    }

    /**
     * Replacing the audience replaces it.
     */
    public function test_replacing_the_audience_replaces_it()
    {
        $existing = Alumni::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2024,
        ]);
        Alumni::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'graduation_year' => 2019,
        ]);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'recipients_count' => 2,
        ]);
        $campaign->recipients()->sync($existing->pluck('id')->all());

        $this->fakeModel([
            $this->asksFor('update_campaign_draft', [
                'campaign_id' => $campaign->id,
                'recipients' => ['mode' => 'filtered', 'graduation_year' => 2019],
            ]),
            $this->says('Remplaces.'),
        ]);

        $this->ask('Envoie-la plutot a la promotion 2019');

        $this->assertSame(3, $campaign->refresh()->recipients_count);
        $this->assertSame(
            [2019, 2019, 2019],
            $campaign->recipients()->pluck('graduation_year')->all(),
        );
    }

    /**
     * A campaign that has left draft cannot be modified.
     */
    public function test_a_campaign_that_has_left_draft_cannot_be_modified()
    {
        Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'subject' => 'Deja partie',
            'status' => 'sent',
            'recipients_count' => 40,
            'sent_at' => now()->subDay(),
        ]);

        $this->fakeModel([
            $this->asksFor('update_campaign_draft', [
                'campaign_id' => $campaign->id,
                'subject' => 'Trop tard',
                'recipients' => ['mode' => 'all'],
            ]),
            $this->says('Cette campagne a deja ete envoyee.'),
        ]);

        $this->ask('Change l objet de la campagne Deja partie');

        $campaign->refresh();
        $this->assertSame('Deja partie', $campaign->subject);
        $this->assertSame(40, $campaign->recipients_count);
        $this->assertSentToModel('n est plus un brouillon');
    }

    /**
     * Another establishments campaign is simply not found.
     */
    public function test_another_establishments_campaign_is_simply_not_found()
    {
        $other = Organization::factory()->create();
        $this->givePlan($other);

        $theirs = Campaign::factory()->create([
            'organization_id' => $other->id,
            'subject' => 'Chez les autres',
            'status' => 'draft',
        ]);

        $this->fakeModel([
            $this->asksFor('update_campaign_draft', [
                'campaign_id' => $theirs->id,
                'subject' => 'Detourne',
            ]),
            $this->says('Cette campagne n existe pas.'),
        ]);

        $this->ask('Modifie la campagne '.$theirs->id);

        $this->assertSame('Chez les autres', $theirs->refresh()->subject);
        $this->assertSentToModel('n existe pas');
        $this->assertNeverSentToModel('Chez les autres');
    }

    /**
     * Campaigns can be found by name before being modified.
     */
    public function test_campaigns_can_be_found_by_name_before_being_modified()
    {
        Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'Invitation au gala',
            'status' => 'draft',
        ]);
        Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'Voeux 2025',
            'status' => 'sent',
        ]);

        $this->fakeModel([
            $this->asksFor('list_campaigns', ['search' => 'gala']),
            $this->says('J ai trouve la campagne du gala.'),
        ]);

        $this->ask('Quelle est ma campagne du gala ?');

        $this->assertSentToModel('Invitation au gala');
        $this->assertSentToModel('"editable":true');
        $this->assertNeverSentToModel('Voeux 2025');
    }

    /**
     * Modifying is refused without the campaign permission.
     */
    public function test_modifying_is_refused_without_the_campaign_permission()
    {
        $limited = $this->staffWith(['dashboard.view', 'campaigns.view', 'assistant.use']);

        $campaign = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'Intacte',
            'status' => 'draft',
        ]);

        $this->fakeModel([
            $this->asksFor('update_campaign_draft', [
                'campaign_id' => $campaign->id,
                'subject' => 'Modifiee',
            ]),
            $this->says('Je ne peux pas.'),
        ]);

        $this->ask('Modifie cette campagne', $limited);

        $this->assertSame('Intacte', $campaign->refresh()->subject);
        $this->assertSentToModel('ne vous est pas accessible');
    }

    /**
     * Staff without the permission are never offered the tool.
     */
    public function test_staff_without_the_permission_are_never_offered_the_tool()
    {
        $limited = $this->staffWith(['dashboard.view', 'events.view', 'assistant.use']);

        $this->fakeModel([
            $this->asksFor('search_alumni', ['search' => 'Benali']),
            $this->says('Je ne peux pas acceder a cette information.'),
        ]);

        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Benali',
        ]);

        $this->ask('Liste les alumni', $limited);

        $this->assertSentToModel('ne vous est pas accessible');
        $this->assertNeverSentToModel('Benali');
    }

    /**
     * Drafting is refused without the campaign permission.
     */
    public function test_drafting_is_refused_without_the_campaign_permission()
    {
        $limited = $this->staffWith(['dashboard.view', 'campaigns.view', 'assistant.use']);

        $this->fakeModel([
            $this->asksFor('draft_campaign', ['subject' => 'Objet', 'content' => 'Corps']),
            $this->says('Je ne peux pas.'),
        ]);

        $this->ask('Redige une campagne', $limited);

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSentToModel('ne vous est pas accessible');
    }

    /**
     * Suggestions only offer what this user could be answered.
     */
    public function test_suggestions_only_offer_what_this_user_could_be_answered()
    {
        $limited = $this->staffWith(['dashboard.view', 'events.view', 'assistant.use']);

        $suggestions = [];

        $this->actingAs($limited)
            ->get(route('assistant.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$suggestions) {
                $suggestions = $page->toArray()['props']['suggestions'];
            });

        $joined = implode(' | ', $suggestions);

        $this->assertStringContainsString('evenements', $joined);
        $this->assertStringNotContainsString('alumni', $joined);
        $this->assertStringNotContainsString('campagne', $joined);
    }

    /**
     * One staff user cannot open another conversation.
     */
    public function test_one_staff_user_cannot_open_another_conversation()
    {
        $colleague = $this->staffWith(['dashboard.view']);

        $theirs = new AiConversation;
        $theirs->organization_id = $this->organization->id;
        $theirs->owner()->associate($colleague);
        $theirs->title = 'Privee';
        $theirs->save();

        $this->actingAs($this->user)
            ->get(route('assistant.show', $theirs))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('assistant.destroy', $theirs))
            ->assertNotFound();

        $this->assertDatabaseHas('ai_conversations', ['id' => $theirs->id]);
    }

    /**
     * An alumni cannot reach the staff assistant.
     */
    public function test_an_alumni_cannot_reach_the_staff_assistant()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $alumni->forceFill([
            'password' => 'motdepasse123',
            'must_change_password' => false,
        ])->save();

        $this->actingAs($alumni, 'alumni')
            ->get(route('assistant.index'))
            ->assertForbidden();

        $this->actingAs($alumni, 'alumni')
            ->post(route('assistant.store'), ['message' => 'Combien d alumni au total ?'])
            ->assertForbidden();

        $this->assertSame(0, AiConversation::acrossOrganizations()->count());
        Http::assertNothingSent();
    }

    /**
     * THE REGRESSION THAT PROMPTED assistant.use AND fromStaff().
     */
    public function test_a_staff_user_also_signed_in_as_an_alumni_sees_only_their_own()
    {
        $alumni = Alumni::factory()->create(['organization_id' => $this->organization->id]);
        $alumni->forceFill(['password' => 'motdepasse123', 'must_change_password' => false])->save();

        $theirs = new AiConversation;
        $theirs->organization_id = $this->organization->id;
        $theirs->owner()->associate($alumni);
        $theirs->title = 'Conversation de l alumni';
        $theirs->save();

        $this->fakeModel([$this->says('Bonjour.')]);

        $this->actingAs($alumni, 'alumni');
        $this->actingAs($this->user, 'web');

        $titles = [];

        $this->get(route('assistant.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$titles) {
                $titles = array_column($page->toArray()['props']['conversations'], 'title');
            });

        $this->assertNotContains('Conversation de l alumni', $titles);

        $this->get(route('assistant.show', $theirs))->assertNotFound();
    }

    /**
     * The assistant needs its own permission.
     */
    public function test_the_assistant_needs_its_own_permission()
    {
        $without = $this->staffWith(['dashboard.view', 'alumni.view']);

        $this->actingAs($without)->get(route('assistant.index'))->assertForbidden();
        $this->actingAs($without)
            ->post(route('assistant.store'), ['message' => 'Bonjour'])
            ->assertForbidden();

        $this->assertSame(0, AiConversation::acrossOrganizations()->count());
        Http::assertNothingSent();
    }

    /**
     * A guest cannot use the staff assistant.
     */
    public function test_a_guest_cannot_use_the_staff_assistant()
    {
        $this->get(route('assistant.index'))->assertRedirect();
        $this->post(route('assistant.store'), ['message' => 'Bonjour'])->assertRedirect();

        Http::assertNothingSent();
    }
}
