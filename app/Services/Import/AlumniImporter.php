<?php

namespace App\Services\Import;

use App\Exceptions\PortalUnavailableException;
use App\Jobs\SendAlumniCredentials;
use App\Models\Alumni;
use App\Models\AlumniImport;
use App\Models\Organization;
use App\Services\AlumniAccountService;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Importing alumni from a spreadsheet (docs/project.md F12).
 */
class AlumniImporter
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly AlumniSheetReader $reader,
        private readonly PlanLimitService $planLimits,
        private readonly AlumniAccountService $accounts,
    ) {}

    /**
     * Read the file and work out what would happen, changing nothing.
     *
     * @return array{ ok: bool, error: string|null, total: int, valid: int, invalid: int, over_limit: int, importable: int, remaining: int|null, errors: list<array{line: int, message: string}>, rows: list<array{line: int, values: array<string, string>}> }
     */
    public function analyse(AlumniImport $import): array
    {
        $path = Storage::disk(AlumniImport::DISK)->path($import->path);

        $read = $this->reader->read($path);

        if (! $read['ok']) {
            return $this->rejected($read['error'] ?? 'Fichier illisible.');
        }

        $organization = $import->organization()->withoutGlobalScopes()->firstOrFail();

        $valid = [];
        $errors = [];
        $seen = [];

        foreach ($read['rows'] as $row) {
            $message = $this->reject($row['values'], $organization, $seen);

            if ($message !== null) {
                $errors[] = ['line' => $row['line'], 'message' => $message];

                continue;
            }

            $seen[mb_strtolower($row['values']['email'])] = true;
            $valid[] = $row;
        }

        $usage = $this->planLimits->usageFor($organization, 'alumni');
        $remaining = $usage['remaining'];

        $importable = $remaining === null ? count($valid) : min(count($valid), $remaining);
        $overLimit = count($valid) - $importable;

        return [
            'ok' => true,
            'error' => null,
            'total' => count($read['rows']),
            'valid' => count($valid),
            'invalid' => count($errors),
            'over_limit' => $overLimit,
            'importable' => $importable,
            'remaining' => $remaining,
            'errors' => $errors,
            'rows' => $valid,
        ];
    }

    /**
     * Create the alumni the analysis says can be created.
     *
     * @return array{imported: int, over_limit: int, invalid: int, portal_available: bool}
     */
    public function run(AlumniImport $import): array
    {
        $analysis = $this->analyse($import);

        if (! $analysis['ok']) {
            $import->forceFill([
                'status' => 'imported',
                'imported_at' => now(),
                'errors' => [['line' => 0, 'message' => $analysis['error']]],
            ])->save();

            return ['imported' => 0, 'over_limit' => 0, 'invalid' => 0, 'portal_available' => false];
        }

        $organization = $import->organization()->withoutGlobalScopes()->firstOrFail();
        $rows = array_slice($analysis['rows'], 0, $analysis['importable']);

        $created = [];

        DB::transaction(function () use ($rows, $analysis, $import, $organization, &$created): void {
            foreach ($rows as $row) {
                $alumni = new Alumni;
                $alumni->organization_id = $organization->id;
                $alumni->import_id = $import->id;
                $alumni->fill($this->attributes($row['values']));
                $alumni->save();

                $created[] = $alumni;
            }

            $import->forceFill([
                'status' => 'imported',
                'imported_at' => now(),
                'rows_total' => $analysis['total'],
                'rows_valid' => $analysis['valid'],
                'rows_invalid' => $analysis['invalid'],
                'rows_imported' => count($rows),
                'rows_over_limit' => $analysis['over_limit'],
                'errors' => $analysis['errors'],
            ])->save();
        });

        return [
            'imported' => count($created),
            'over_limit' => $analysis['over_limit'],
            'invalid' => $analysis['invalid'],
            'portal_available' => $this->accounts->isAvailableTo($organization),
        ];
    }

    /**
     * Create portal accounts for the alumni this import added, and mail each of them their credentials (docs/project.md F12).
     *
     * @return int how many alumni were queued
     */
    public function createAccounts(AlumniImport $import): int
    {
        $organization = $import->organization()->withoutGlobalScopes()->firstOrFail();

        if (! $this->accounts->isAvailableTo($organization)) {
            throw new PortalUnavailableException;
        }

        $ids = Alumni::acrossOrganizations()
            ->where('import_id', $import->id)
            ->whereNull('password')
            ->pluck('id');

        foreach ($ids as $id) {
            SendAlumniCredentials::dispatch((int) $id);
        }

        return $ids->count();
    }

    /**
     * How many of this import's alumni are still without an account.
     */
    public function alumniWithoutAccounts(AlumniImport $import): int
    {
        return Alumni::acrossOrganizations()
            ->where('import_id', $import->id)
            ->whereNull('password')
            ->count();
    }

    /**
     * Undo an import: the alumni it created go to the corbeille.
     */
    public function undo(AlumniImport $import): int
    {
        $count = 0;

        DB::transaction(function () use ($import, &$count): void {
            $alumni = Alumni::acrossOrganizations()
                ->where('import_id', $import->id)
                ->get();

            foreach ($alumni as $one) {
                $one->delete();
                $count++;
            }

            $import->forceFill(['status' => 'undone', 'undone_at' => now()])->save();
        });

        return $count;
    }

    /**
     * Why this row cannot become an alumni, or null if it can.
     *
     * @param  array<string, string>  $values
     * @param  array<string, bool>  $seen
     */
    private function reject(array $values, Organization $organization, array $seen): ?string
    {
        $validator = Validator::make($values, [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'degree' => ['nullable', 'string', 'max:120'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 10)],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return (string) $validator->errors()->first();
        }

        if (isset($seen[mb_strtolower($values['email'])])) {
            return 'Cette adresse apparait deux fois dans le fichier.';
        }

        $exists = Alumni::acrossOrganizations()
            ->withTrashed()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($values['email'])])
            ->exists();

        if ($exists) {
            return 'Un alumni de votre organisation utilise deja cette adresse.';
        }

        return null;
    }

    /**
     * The custom attribute names for the validation errors.
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function attributes(array $values): array
    {
        $attributes = [];

        foreach (AlumniImportTemplate::COLUMNS as $attribute) {
            $value = $values[$attribute] ?? '';
            $attributes[$attribute] = $value === '' ? null : $value;
        }

        $attributes['graduation_year'] = $attributes['graduation_year'] === null
            ? null
            : (int) $attributes['graduation_year'];

        return $attributes;
    }

    /**
     * Record a row that could not be imported and why.
     *
     * @return array{ok: bool, error: string, total: int, valid: int, invalid: int, over_limit: int, importable: int, remaining: int|null, errors: list<array{line: int, message: string}>, rows: list<array{line: int, values: array<string, string>}>}
     */
    private function rejected(string $message): array
    {
        return [
            'ok' => false,
            'error' => $message,
            'total' => 0,
            'valid' => 0,
            'invalid' => 0,
            'over_limit' => 0,
            'importable' => 0,
            'remaining' => null,
            'errors' => [],
            'rows' => [],
        ];
    }
}
