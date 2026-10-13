<?php

namespace App\Http\Controllers;

use App\Exceptions\PortalUnavailableException;
use App\Models\Alumni;
use App\Models\AlumniImport;
use App\Services\AlumniAccountService;
use App\Services\Import\AlumniImporter;
use App\Services\Import\AlumniImportTemplate;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Importing alumni from a spreadsheet (docs/project.md F12).
 */
class AlumniImportController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly AlumniImporter $importer,
        private readonly PlanLimitService $planLimits,
        private readonly AlumniAccountService $accounts,
    ) {}

    /**
     * Show the import screen with the pending import, if any.
     */
    public function index(Request $request): Response
    {
        $this->authorize('import', Alumni::class);

        $pending = AlumniImport::query()->where('status', 'pending')->latest()->first();

        return Inertia::render('alumni/import', [
            'usage' => $this->planLimits->usageFor($request->user()->organization, 'alumni'),
            'columns' => AlumniImportTemplate::headings(),
            'requiredColumns' => $this->requiredHeadings(),
            'maxKilobytes' => AlumniImport::MAX_KILOBYTES,
            'extensions' => AlumniImport::EXTENSIONS,
            'portalAvailable' => $this->accounts->isAvailableTo($request->user()->organization),
            'offerAccounts' => $request->session()->get('offerAccounts'),
            'pending' => $pending === null ? null : [
                ...$this->summarise($pending),
                'analysis' => $this->importer->analyse($pending),
            ],
            'history' => AlumniImport::query()
                ->whereIn('status', ['imported', 'undone'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AlumniImport $import): array => $this->summarise($import))
                ->all(),
        ]);
    }

    /**
     * Download the spreadsheet template.
     */
    public function template(AlumniImportTemplate $template): BinaryFileResponse
    {
        $this->authorize('import', Alumni::class);

        $path = tempnam(sys_get_temp_dir(), 'aeterna-modele-').'.xlsx';

        $template->write($path);

        return response()
            ->download($path, $template->filename())
            ->deleteFileAfterSend();
    }

    /**
     * Accept a file and analyse it.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('import', Alumni::class);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.AlumniImport::MAX_KILOBYTES,
                Rule::file()->extensions(AlumniImport::EXTENSIONS),
            ],
        ], [
            'file.max' => 'Le fichier ne doit pas depasser :max Ko.',
            'file.extensions' => 'Formats acceptes : '.implode(', ', AlumniImport::EXTENSIONS).'.',
        ], ['file' => 'fichier']);

        $this->discardPending();

        $file = $request->file('file');

        $path = $file->storeAs(
            AlumniImport::DIRECTORY,
            Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
            AlumniImport::DISK,
        );

        $import = new AlumniImport;
        $import->organization_id = $request->user()->organization_id;
        $import->forceFill([
            'user_id' => $request->user()->id,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'path' => $path,
            'size_bytes' => $file->getSize(),
            'status' => 'pending',
        ])->save();

        $analysis = $this->importer->analyse($import);

        if (! $analysis['ok']) {
            $import->deleteFile();
            $import->delete();

            return back()->withErrors(['file' => $analysis['error']]);
        }

        return back()->with('success', 'Fichier analyse. Verifiez le resume avant d importer.');
    }

    /**
     * Run the import the analysis described.
     */
    public function run(Request $request, AlumniImport $import): RedirectResponse
    {
        $this->authorize('import', Alumni::class);

        abort_unless($import->status === 'pending', 404);

        $result = $this->importer->run($import);

        $message = "{$result['imported']} alumni importes.";

        if ($result['invalid'] > 0) {
            $message .= " {$result['invalid']} ligne(s) ignorees.";
        }

        if ($result['over_limit'] > 0) {
            $message .= " {$result['over_limit']} ligne(s) au-dela de votre plan n ont pas ete importees.";
        }

        $response = back()->with($result['imported'] > 0 ? 'success' : 'error', $message);

        if ($result['imported'] > 0 && $result['portal_available']) {
            $response->with('offerAccounts', $import->id);
        }

        return $response;
    }

    /**
     * Create portal accounts for this import's alumni and mail them their credentials.
     */
    public function accounts(Request $request, AlumniImport $import): RedirectResponse
    {
        $this->authorize('inviteAny', Alumni::class);

        abort_unless($import->status === 'imported', 404);

        try {
            $queued = $this->importer->createAccounts($import);
        } catch (PortalUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($queued === 0) {
            return back()->with('error', 'Ces alumni ont deja tous un compte.');
        }

        return back()->with(
            'success',
            "{$queued} comptes en cours de creation. Chaque alumni recevra ses identifiants par e-mail.",
        );
    }

    /**
     * Discard the uploaded file.
     */
    public function destroy(Request $request, AlumniImport $import): RedirectResponse
    {
        $this->authorize('import', Alumni::class);

        $this->confirmPassword($request);

        $import->deleteFile();

        if ($import->status === 'pending') {
            $import->delete();
        }

        return back()->with('success', 'Fichier supprime.');
    }

    /**
     * Undo an import: the alumni it created go to the corbeille.
     */
    public function undo(Request $request, AlumniImport $import): RedirectResponse
    {
        $this->authorize('import', Alumni::class);

        abort_unless($import->status === 'imported', 404);

        $this->confirmPassword($request);

        $count = $this->importer->undo($import);

        return back()->with(
            'success',
            "{$count} alumni deplaces vers la corbeille. Vous pouvez les restaurer depuis la corbeille.",
        );
    }

    /**
     * Ask for the signed-in user's own password before something destructive.
     */
    private function confirmPassword(Request $request): void
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.current_password' => 'Ce mot de passe est incorrect.',
            'password.required' => 'Saisissez votre mot de passe pour confirmer.',
        ]);
    }

    /**
     * Remove the pending import and its uploaded file.
     */
    private function discardPending(): void
    {
        AlumniImport::query()
            ->where('status', 'pending')
            ->get()
            ->each(function (AlumniImport $import): void {
                $import->deleteFile();
                $import->delete();
            });
    }

    /**
     * The column headings an import file must carry.
     *
     * @return list<string>
     */
    private function requiredHeadings(): array
    {
        $headings = [];

        foreach (AlumniImportTemplate::COLUMNS as $heading => $attribute) {
            if (in_array($attribute, AlumniImportTemplate::REQUIRED, true)) {
                $headings[] = $heading;
            }
        }

        return $headings;
    }

    /**
     * The pending import as the screen displays it.
     *
     * @return array<string, mixed>
     */
    private function summarise(AlumniImport $import): array
    {
        return [
            'id' => $import->id,
            'original_name' => $import->original_name,
            'size_bytes' => $import->size_bytes,
            'status' => $import->status,
            'rows_total' => $import->rows_total,
            'rows_imported' => $import->rows_imported,
            'rows_invalid' => $import->rows_invalid,
            'rows_over_limit' => $import->rows_over_limit,
            'errors' => $import->errors ?? [],
            'without_account' => $import->status === 'imported'
                ? $this->importer->alumniWithoutAccounts($import)
                : 0,
            'imported_at' => $import->imported_at?->toIso8601String(),
            'undone_at' => $import->undone_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
            'file_exists' => $import->fileExists(),
            'uploaded_by' => $import->user()->withoutGlobalScopes()->value('name'),
        ];
    }
}
