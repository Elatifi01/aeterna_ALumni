<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AI\AiProvider;
use App\Services\AI\OllamaProvider;
use App\Services\AI\Tools\DraftCampaignTool;
use App\Services\AI\Tools\GetAlumniStatisticsTool;
use App\Services\AI\Tools\GetMyApplicationsTool;
use App\Services\AI\Tools\GetMyProfileTool;
use App\Services\AI\Tools\GetUpcomingEventsTool;
use App\Services\AI\Tools\ListCampaignsTool;
use App\Services\AI\Tools\SearchAlumniTool;
use App\Services\AI\Tools\SearchJobsTool;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\UpdateCampaignDraftTool;
use App\Services\EmploiMa\CreditBudget;
use App\Services\EmploiMa\EmploiMaClient;
use App\Services\Jobs\JobSearch;
use App\Services\Jobs\ParseBotSource;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerAssistant();
    }

    /**
     * Wire the Aeterna Assistant (docs/project.md F14).
     */
    protected function registerAssistant(): void
    {
        $this->app->bind(AiProvider::class, fn (): AiProvider => match (config('services.ai.provider')) {
            default => new OllamaProvider,
        });

        $this->app->singleton(JobSearch::class, function ($app): JobSearch {
            $sources = [$app->make(EmploiMaClient::class)];

            foreach ((array) config('services.jobs.sources', []) as $key => $config) {
                $sources[] = new ParseBotSource((string) $key, (array) $config, $app->make(CreditBudget::class));
            }

            return new JobSearch($sources);
        });

        $this->app->singleton(ToolRegistry::class, fn ($app): ToolRegistry => new ToolRegistry([
            $app->make(SearchJobsTool::class),
            $app->make(GetUpcomingEventsTool::class),
            $app->make(GetMyProfileTool::class),
            $app->make(GetMyApplicationsTool::class),
            $app->make(SearchAlumniTool::class),
            $app->make(GetAlumniStatisticsTool::class),
            $app->make(DraftCampaignTool::class),
            $app->make(ListCampaignsTool::class),
            $app->make(UpdateCampaignDraftTool::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureGuestRedirects();
    }

    /**
     * Where an already-signed-in visitor is sent away from a login screen.
     */
    protected function configureGuestRedirects(): void
    {
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request): string => $request->is('portail/*')
                ? route('portal.dashboard')
                : route('dashboard'),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register one Gate per permission of the catalogue (docs/project.md §5.4).
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        foreach (Permissions::slugs() as $slug) {
            Gate::define($slug, fn (User $user): bool => $user->hasPermission($slug));
        }
    }
}
