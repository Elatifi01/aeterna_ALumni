<?php

namespace Tests\Feature\Alumni;

use App\Models\Alumni;
use App\Models\AlumniImport;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Import\AlumniExporter;
use App\Services\Import\AlumniImportTemplate;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Exporting the alumni list (docs/project.md F13).
 */
class AlumniExportTest extends TestCase
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

        $this->organization = Organization::factory()->create(['name' => 'Universite Test']);

        $plan = Plan::factory()->create(['max_alumni' => 1000, 'alumni_portal' => false]);
        Subscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Download the export and read it back as rows.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<int, string>>
     */
    private function exportedRows(array $query = []): array
    {
        $response = $this->actingAs($this->admin)->get(route('alumni.export', $query));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'aeterna-test-export-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::createReaderForFile($path)->load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        @unlink($path);

        return array_map(
            fn (array $row): array => array_map(fn ($cell) => $cell ?? '', $row),
            $rows,
        );
    }

    /**
     * The export has the import columns first.
     */
    public function test_the_export_has_the_import_columns_first()
    {
        Alumni::factory()->create(['organization_id' => $this->organization->id]);

        $rows = $this->exportedRows();
        $headings = $rows[0];

        foreach (AlumniImportTemplate::headings() as $index => $heading) {
            $this->assertSame($heading, $headings[$index]);
        }

        $this->assertSame('Compte portail', $headings[count(AlumniImportTemplate::headings())]);
    }

    /**
     * Every alumni appears with their details.
     */
    public function test_every_alumni_appears_with_their_details()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Amina',
            'last_name' => 'Bennani',
            'email' => 'amina@example.test',
            'phone' => '0612345678',
            'degree' => 'Master Informatique',
            'graduation_year' => 2021,
            'company' => 'Atlas Digital',
            'job_title' => 'Developpeuse',
            'city' => 'Casablanca',
        ]);

        $rows = $this->exportedRows();

        $this->assertCount(2, $rows, 'one heading row and one alumni');
        $this->assertSame(
            ['Amina', 'Bennani', 'amina@example.test', '0612345678', 'Master Informatique', '2021', 'Atlas Digital', 'Developpeuse', 'Casablanca'],
            array_slice($rows[1], 0, 9),
        );
    }

    /**
     * A leading zero on a telephone survives.
     */
    public function test_a_leading_zero_on_a_telephone_survives()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0612345678',
        ]);

        $rows = $this->exportedRows();

        $this->assertSame('0612345678', $rows[1][3]);
    }

    /**
     * The export follows the list filters.
     */
    public function test_the_export_follows_the_list_filters()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Bennani',
            'company' => 'Atlas Digital',
        ]);
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Alaoui',
            'company' => 'BMCE',
        ]);

        $rows = $this->exportedRows(['company' => 'Atlas Digital']);

        $this->assertCount(2, $rows);
        $this->assertSame('Bennani', $rows[1][1]);
    }

    /**
     * The search filter applies too.
     */
    public function test_the_search_filter_applies_too()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Amina',
        ]);
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Karim',
        ]);

        $rows = $this->exportedRows(['search' => 'Amina']);

        $this->assertCount(2, $rows);
        $this->assertSame('Amina', $rows[1][0]);
    }

    /**
     * Another organizations alumni are never exported.
     */
    public function test_another_organizations_alumni_are_never_exported()
    {
        $other = Organization::factory()->create();

        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Notre',
        ]);
        Alumni::factory()->create([
            'organization_id' => $other->id,
            'last_name' => 'Ailleurs',
        ]);

        $rows = $this->exportedRows();

        $this->assertCount(2, $rows, 'the heading and one alumni only');
        $this->assertSame('Notre', $rows[1][1]);
    }

    /**
     * Alumni in the corbeille are not exported.
     */
    public function test_alumni_in_the_corbeille_are_not_exported()
    {
        Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Actif',
        ]);

        $binned = Alumni::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Supprime',
        ]);
        $binned->delete();

        $rows = $this->exportedRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Actif', $rows[1][1]);
    }

    /**
     * The portal columns describe the account.
     */
    public function test_the_portal_columns_describe_the_account()
    {
        $withAccount = Alumni::factory()->create(['organization_id' => $this->organization->id, 'last_name' => 'Avec']);
        $withAccount->forceFill([
            'password' => 'motdepasse123',
            'portal_access' => true,
            'last_login_at' => now()->setDate(2026, 5, 4)->setTime(9, 30),
        ])->save();

        $suspended = Alumni::factory()->create(['organization_id' => $this->organization->id, 'last_name' => 'Suspendu']);
        $suspended->forceFill(['password' => 'motdepasse123', 'portal_access' => false])->save();

        Alumni::factory()->create(['organization_id' => $this->organization->id, 'last_name' => 'Sans']);

        $rows = $this->exportedRows();
        $byName = [];

        foreach (array_slice($rows, 1) as $row) {
            $byName[$row[1]] = array_slice($row, 9, 3);
        }

        $this->assertSame(['Oui', 'Actif', '2026-05-04 09:30'], $byName['Avec']);
        $this->assertSame(['Oui', 'Suspendu', ''], $byName['Suspendu']);
        $this->assertSame(['Non', '', ''], $byName['Sans']);
    }

    /**
     * An exported file can be imported again.
     */
    public function test_an_exported_file_can_be_imported_again()
    {
        foreach ([['Amina', 'Bennani', 'amina@example.test'], ['Karim', 'Alaoui', 'karim@example.test']] as [$first, $last, $email]) {
            Alumni::factory()->create([
                'organization_id' => $this->organization->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'graduation_year' => 2021,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('alumni.export'));
        $path = tempnam(sys_get_temp_dir(), 'aeterna-roundtrip-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $other = Organization::factory()->create();
        $plan = Plan::factory()->create(['max_alumni' => 1000, 'alumni_portal' => false]);
        Subscription::factory()->create([
            'organization_id' => $other->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $role = Role::factory()->create(['organization_id' => $other->id]);
        $role->permissions()->sync(Permission::pluck('id'));
        $theirAdmin = User::factory()->create(['organization_id' => $other->id, 'role_id' => $role->id]);

        $this->actingAs($theirAdmin)
            ->post(route('alumni.import.store'), [
                'file' => new UploadedFile($path, 'export.xlsx', null, null, true),
            ])
            ->assertSessionHasNoErrors();

        $import = AlumniImport::acrossOrganizations()->where('organization_id', $other->id)->firstOrFail();

        $this->actingAs($theirAdmin)->post(route('alumni.import.run', $import));

        $this->assertSame(2, Alumni::acrossOrganizations()->where('organization_id', $other->id)->count());
        $this->assertSame(
            2021,
            Alumni::acrossOrganizations()->where('organization_id', $other->id)->value('graduation_year'),
        );

        @unlink($path);
    }

    /**
     * Exporting requires the export permission.
     */
    public function test_exporting_requires_the_export_permission()
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['alumni.view'])->pluck('id'),
        );

        $staff = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($staff)->get(route('alumni.export'))->assertForbidden();
    }

    /**
     * The filename names the organization and the day.
     */
    public function test_the_filename_names_the_organization_and_the_day()
    {
        $name = app(AlumniExporter::class)->filename($this->organization);

        $this->assertSame('alumni-universite-test-'.now()->format('Y-m-d').'.xlsx', $name);
    }

    /**
     * An empty list still produces a usable file.
     */
    public function test_an_empty_list_still_produces_a_usable_file()
    {
        $rows = $this->exportedRows();

        $this->assertCount(1, $rows);
        $this->assertSame(AlumniImportTemplate::headings()[0], $rows[0][0]);
    }
}
