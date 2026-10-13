<?php

namespace Tests\Feature\Alumni;

use App\Jobs\SendAlumniCredentials;
use App\Mail\AlumniCredentialsMail;
use App\Models\Alumni;
use App\Models\AlumniImport;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AlumniAccountService;
use App\Services\Import\AlumniImportTemplate;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Importing alumni from a spreadsheet (docs/project.md F12).
 */
class AlumniImportTest extends TestCase
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
        Queue::fake();
        Storage::fake(AlumniImport::DISK);

        foreach (Permissions::rows() as $row) {
            Permission::firstOrCreate(['slug' => $row['slug']], $row);
        }

        $this->organization = Organization::factory()->create();
        $this->givePlan(maxAlumni: 100);

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Subscribe the organization to a plan.
     */
    private function givePlan(?int $maxAlumni, bool $portal = false): void
    {
        Subscription::query()->where('organization_id', $this->organization->id)->delete();

        $plan = Plan::factory()->create([
            'max_alumni' => $maxAlumni,
            'alumni_portal' => $portal,
        ]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);
    }

    /**
     * A spreadsheet in the template's own shape.
     *
     * @param  list<array<int, string>>  $rows
     */
    private function sheet(array $rows, ?array $headings = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headings ?? AlumniImportTemplate::headings() as $index => $heading) {
            $sheet->setCellValue([$index + 1, 1], $heading);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'aeterna-test-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'alumni.xlsx', null, null, true);
    }

    /**
     * Upload a spreadsheet as the administrator.
     *
     * @param  list<array<int, string>>  $rows
     */
    private function upload(array $rows, ?array $headings = null): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('alumni.import.store'), [
            'file' => $this->sheet($rows, $headings),
        ]);
    }

    /**
     * The organization's pending import.
     */
    private function pending(): AlumniImport
    {
        return AlumniImport::acrossOrganizations()->where('status', 'pending')->firstOrFail();
    }

    /**
     * One valid row.
     */
    private function row(string $first, string $last, string $email): array
    {
        return [$first, $last, $email, '0612345678', 'Master', '2021', 'Atlas', 'Dev', 'Casablanca'];
    }

    /**
     * A good file is analysed without importing anything.
     */
    public function test_a_good_file_is_analysed_without_importing_anything()
    {
        $this->upload([
            $this->row('Amina', 'Bennani', 'amina@example.test'),
            $this->row('Karim', 'Alaoui', 'karim@example.test'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Alumni::acrossOrganizations()->count());

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('alumni/import')
                ->where('pending.analysis.total', 2)
                ->where('pending.analysis.valid', 2)
                ->where('pending.analysis.invalid', 0)
                ->where('pending.analysis.importable', 2)
            );
    }

    /**
     * Running the import creates the alumni.
     */
    public function test_running_the_import_creates_the_alumni()
    {
        $this->upload([
            $this->row('Amina', 'Bennani', 'amina@example.test'),
            $this->row('Karim', 'Alaoui', 'karim@example.test'),
        ]);

        $import = $this->pending();

        $this->actingAs($this->admin)
            ->post(route('alumni.import.run', $import))
            ->assertSessionHas('success');

        $this->assertSame(2, Alumni::acrossOrganizations()->count());

        $import->refresh();
        $this->assertSame('imported', $import->status);
        $this->assertSame(2, $import->rows_imported);

        $this->assertSame(2, Alumni::acrossOrganizations()->where('import_id', $import->id)->count());
    }

    /**
     * The heading line is not counted as an alumni.
     */
    public function test_the_heading_line_is_not_counted_as_an_alumni()
    {
        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.total', 1)
            );
    }

    /**
     * Rows beyond the plan are refused and the rest still import.
     */
    public function test_rows_beyond_the_plan_are_refused_and_the_rest_still_import()
    {
        $this->givePlan(maxAlumni: 100);
        Alumni::factory()->count(97)->create(['organization_id' => $this->organization->id]);

        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $rows[] = $this->row('Prenom'.$i, 'Nom'.$i, "import{$i}@example.test");
        }

        $this->upload($rows);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.valid', 10)
                ->where('pending.analysis.importable', 3)
                ->where('pending.analysis.over_limit', 7)
            );

        $this->actingAs($this->admin)->post(route('alumni.import.run', $this->pending()));

        $this->assertSame(100, Alumni::acrossOrganizations()->count());
    }

    /**
     * An unlimited plan imports everything.
     */
    public function test_an_unlimited_plan_imports_everything()
    {
        $this->givePlan(maxAlumni: null);

        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $rows[] = $this->row('Prenom'.$i, 'Nom'.$i, "libre{$i}@example.test");
        }

        $this->upload($rows);
        $this->actingAs($this->admin)->post(route('alumni.import.run', $this->pending()));

        $this->assertSame(12, Alumni::acrossOrganizations()->count());
    }

    /**
     * Bad rows are skipped and reported with their line number.
     */
    public function test_bad_rows_are_skipped_and_reported_with_their_line_number()
    {
        $this->upload([
            $this->row('Amina', 'Bennani', 'amina@example.test'),
            $this->row('Sans', 'Email', 'pas-une-adresse'),
            ['', 'Nom', 'sans.prenom@example.test'],
            $this->row('Karim', 'Alaoui', 'karim@example.test'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.valid', 2)
                ->where('pending.analysis.invalid', 2)
                ->where('pending.analysis.errors.0.line', 3)
                ->where('pending.analysis.errors.1.line', 4)
            );

        $this->actingAs($this->admin)->post(route('alumni.import.run', $this->pending()));

        $this->assertSame(2, Alumni::acrossOrganizations()->count());
    }

    /**
     * An address repeated inside the file is rejected once.
     */
    public function test_an_address_repeated_inside_the_file_is_rejected_once()
    {
        $this->upload([
            $this->row('Amina', 'Bennani', 'meme@example.test'),
            $this->row('Autre', 'Personne', 'MEME@example.test'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.valid', 1)
                ->where('pending.analysis.invalid', 1)
            );
    }

    /**
     * An address already in the organization is rejected.
     */
    public function test_an_address_already_in_the_organization_is_rejected()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'deja@example.test',
        ]);

        $this->upload([$this->row('Amina', 'Bennani', 'deja@example.test')]);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.valid', 0)
                ->where('pending.analysis.invalid', 1)
            );
    }

    /**
     * An address in the corbeille is still rejected.
     */
    public function test_an_address_in_the_corbeille_is_still_rejected()
    {
        $trashed = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'corbeille@example.test',
        ]);
        $trashed->delete();

        $this->upload([$this->row('Amina', 'Bennani', 'corbeille@example.test')]);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.invalid', 1)
            );
    }

    /**
     * The same address in another organization is fine.
     */
    public function test_the_same_address_in_another_organization_is_fine()
    {
        $other = Organization::factory()->create();
        Alumni::factory()->create([
            'organization_id' => $other->id,
            'email' => 'partage@example.test',
        ]);

        $this->upload([$this->row('Amina', 'Bennani', 'partage@example.test')]);

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.valid', 1)
            );
    }

    /**
     * A file whose columns do not match the model is refused.
     */
    public function test_a_file_whose_columns_do_not_match_the_model_is_refused()
    {
        $this->upload(
            [['a', 'b', 'c']],
            ['Colonne 1', 'Colonne 2', 'Colonne 3'],
        )->assertSessionHasErrors('file');

        $this->assertSame(0, AlumniImport::acrossOrganizations()->count());
    }

    /**
     * Headings are matched loosely.
     */
    public function test_headings_are_matched_loosely()
    {
        $this->upload(
            [$this->row('Amina', 'Bennani', 'amina@example.test')],
            ['prénom', 'NOM', 'e mail', 'Téléphone', 'Diplôme', 'Promotion', 'Entreprise', 'Poste', 'Ville'],
        )->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pending.analysis.valid', 1)
            );
    }

    /**
     * A non spreadsheet is refused.
     */
    public function test_a_non_spreadsheet_is_refused()
    {
        $this->actingAs($this->admin)
            ->post(route('alumni.import.store'), [
                'file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    /**
     * The model file can be downloaded and reads back cleanly.
     */
    public function test_the_model_file_can_be_downloaded_and_reads_back_cleanly()
    {
        $response = $this->actingAs($this->admin)->get(route('alumni.import.template'));

        $response->assertOk();
        $response->assertDownload('modele-import-alumni.xlsx');
    }

    /**
     * Discarding the file needs the password.
     */
    public function test_discarding_the_file_needs_the_password()
    {
        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();

        $this->actingAs($this->admin)
            ->delete(route('alumni.import.destroy', $import), ['password' => 'faux'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('alumni_imports', ['id' => $import->id]);

        $this->actingAs($this->admin)
            ->delete(route('alumni.import.destroy', $import), ['password' => 'password'])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('alumni_imports', ['id' => $import->id]);
        Storage::disk(AlumniImport::DISK)->assertMissing($import->path);
    }

    /**
     * Undoing an import needs the password and bins the alumni.
     */
    public function test_undoing_an_import_needs_the_password_and_bins_the_alumni()
    {
        $this->upload([
            $this->row('Amina', 'Bennani', 'amina@example.test'),
            $this->row('Karim', 'Alaoui', 'karim@example.test'),
        ]);

        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $this->assertSame(2, Alumni::acrossOrganizations()->count());

        $this->actingAs($this->admin)
            ->post(route('alumni.import.undo', $import), ['password' => 'faux'])
            ->assertSessionHasErrors('password');

        $this->assertSame(2, Alumni::acrossOrganizations()->count());

        $this->actingAs($this->admin)
            ->post(route('alumni.import.undo', $import), ['password' => 'password'])
            ->assertSessionHas('success');

        $this->assertSame(0, Alumni::acrossOrganizations()->count());
        $this->assertSame(2, Alumni::acrossOrganizations()->onlyTrashed()->count());
        $this->assertSame('undone', $import->refresh()->status);
    }

    /**
     * An undo only removes what that import created.
     */
    public function test_an_undo_only_removes_what_that_import_created()
    {
        $byHand = Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $this->actingAs($this->admin)
            ->post(route('alumni.import.undo', $import), ['password' => 'password']);

        $this->assertSame(1, Alumni::acrossOrganizations()->count());
        $this->assertNotNull(Alumni::acrossOrganizations()->find($byHand->id));
    }

    /**
     * Importing requires the import permission.
     */
    public function test_importing_requires_the_import_permission()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['alumni.view', 'alumni.create'])->pluck('id'),
        );

        $staff = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($staff)->get(route('alumni.import.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('alumni.import.template'))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('alumni.import.store'), ['file' => $this->sheet([])])
            ->assertForbidden();
    }

    /**
     * An import of another organization is unreachable.
     */
    public function test_an_import_of_another_organization_is_unreachable()
    {
        $other = Organization::factory()->create();

        $stranger = new AlumniImport;
        $stranger->organization_id = $other->id;
        $stranger->forceFill([
            'original_name' => 'ailleurs.xlsx',
            'path' => 'alumni-imports/ailleurs.xlsx',
            'status' => 'pending',
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('alumni.import.run', $stranger))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('alumni.import.destroy', $stranger), ['password' => 'password'])
            ->assertNotFound();
    }

    /**
     * Importing never creates accounts on its own.
     */
    public function test_importing_never_creates_accounts_on_its_own()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([
            $this->row('Amina', 'Bennani', 'amina@example.test'),
            $this->row('Karim', 'Alaoui', 'karim@example.test'),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('alumni.import.run', $this->pending()));

        $this->assertSame(0, Alumni::acrossOrganizations()->whereNotNull('password')->count());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();

        $response->assertSessionHas('offerAccounts');
    }

    /**
     * The screen offers the accounts after an import.
     */
    public function test_the_screen_offers_the_accounts_after_an_import()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $this->actingAs($this->admin)
            ->get(route('alumni.import.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('portalAvailable', true)
                ->where('offerAccounts', $import->id)
                ->where('history.0.without_account', 1)
            );
    }

    /**
     * Saying yes queues one job per alumni.
     */
    public function test_saying_yes_queues_one_job_per_alumni()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([
            $this->row('Amina', 'Bennani', 'amina@example.test'),
            $this->row('Karim', 'Alaoui', 'karim@example.test'),
        ]);

        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $this->actingAs($this->admin)
            ->post(route('alumni.import.accounts', $import))
            ->assertSessionHas('success');

        Queue::assertPushed(SendAlumniCredentials::class, 2);
    }

    /**
     * The queued job creates the account and mails it.
     */
    public function test_the_queued_job_creates_the_account_and_mails_it()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $alumni = Alumni::acrossOrganizations()->firstOrFail();
        $this->assertFalse($alumni->has_account);

        (new SendAlumniCredentials($alumni->id))->handle(app(AlumniAccountService::class));

        $this->assertTrue($alumni->refresh()->has_account);
        Mail::assertSent(AlumniCredentialsMail::class);
    }

    /**
     * The job skips an alumni who already has an account.
     */
    public function test_the_job_skips_an_alumni_who_already_has_an_account()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $alumni = Alumni::acrossOrganizations()->firstOrFail();

        (new SendAlumniCredentials($alumni->id))->handle(app(AlumniAccountService::class));
        $password = $alumni->refresh()->password;

        (new SendAlumniCredentials($alumni->id))->handle(app(AlumniAccountService::class));

        $this->assertSame($password, $alumni->refresh()->password);
        Mail::assertSentCount(1);
    }

    /**
     * Asking twice reports that there is nothing left to do.
     */
    public function test_asking_twice_reports_that_there_is_nothing_left_to_do()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $this->actingAs($this->admin)->post(route('alumni.import.accounts', $import));

        Alumni::acrossOrganizations()->each(
            fn (Alumni $one) => app(AlumniAccountService::class)->provision($one),
        );

        $this->actingAs($this->admin)
            ->post(route('alumni.import.accounts', $import))
            ->assertSessionHas('error');
    }

    /**
     * A plan without the portal is never offered accounts.
     */
    public function test_a_plan_without_the_portal_is_never_offered_accounts()
    {
        $this->givePlan(maxAlumni: 100, portal: false);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();

        $this->actingAs($this->admin)
            ->post(route('alumni.import.run', $import))
            ->assertSessionMissing('offerAccounts');

        $this->actingAs($this->admin)
            ->post(route('alumni.import.accounts', $import))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    /**
     * Creating the accounts requires the invite permission.
     */
    public function test_creating_the_accounts_requires_the_invite_permission()
    {
        $this->givePlan(maxAlumni: 100, portal: true);

        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $import = $this->pending();
        $this->actingAs($this->admin)->post(route('alumni.import.run', $import));

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['alumni.view', 'alumni.import'])->pluck('id'),
        );

        $importer = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($importer)
            ->post(route('alumni.import.accounts', $import))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    /**
     * A second upload replaces the first pending one.
     */
    public function test_a_second_upload_replaces_the_first_pending_one()
    {
        $this->upload([$this->row('Amina', 'Bennani', 'amina@example.test')]);
        $first = $this->pending();

        $this->upload([$this->row('Karim', 'Alaoui', 'karim@example.test')]);

        $this->assertSame(1, AlumniImport::acrossOrganizations()->where('status', 'pending')->count());
        $this->assertDatabaseMissing('alumni_imports', ['id' => $first->id]);
        Storage::disk(AlumniImport::DISK)->assertMissing($first->path);
    }
}
