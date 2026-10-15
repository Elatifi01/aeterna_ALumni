<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\AlumniAccountController;
use App\Http\Controllers\AlumniController;
use App\Http\Controllers\AlumniImportController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::post('demo', [LandingController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('demo-requests.store');

Route::get('inscription/{plan:slug}', [SignupController::class, 'create'])
    ->middleware('guest')
    ->name('signup.create');
Route::post('inscription/{plan:slug}', [SignupController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('signup.store');

Route::get('essai-termine', TrialController::class)
    ->middleware('auth')
    ->name('trial.ended');

Route::middleware(['auth', 'organization'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Payments belong to the signed in organization; they were reachable by anyone before.
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::get('assistant/{conversation}', [AssistantController::class, 'index'])
        ->whereNumber('conversation')
        ->name('assistant.show');
    Route::post('assistant', [AssistantController::class, 'store'])->name('assistant.store');
    Route::delete('assistant/{conversation}', [AssistantController::class, 'destroy'])
        ->whereNumber('conversation')
        ->name('assistant.destroy');

    Route::get('alumni/import', [AlumniImportController::class, 'index'])
        ->name('alumni.import.index');
    Route::get('alumni/import/modele', [AlumniImportController::class, 'template'])
        ->name('alumni.import.template');
    Route::post('alumni/import', [AlumniImportController::class, 'store'])
        ->name('alumni.import.store');
    Route::post('alumni/import/{import}/lancer', [AlumniImportController::class, 'run'])
        ->name('alumni.import.run');
    Route::delete('alumni/import/{import}', [AlumniImportController::class, 'destroy'])
        ->name('alumni.import.destroy');
    Route::post('alumni/import/{import}/annuler', [AlumniImportController::class, 'undo'])
        ->name('alumni.import.undo');
    Route::post('alumni/import/{import}/comptes', [AlumniImportController::class, 'accounts'])
        ->name('alumni.import.accounts');

    Route::get('alumni/export', [AlumniController::class, 'export'])
        ->name('alumni.export');

    Route::delete('alumni/selection', [AlumniController::class, 'destroySelection'])
        ->name('alumni.destroy-selection');
    Route::delete('events/selection', [EventController::class, 'destroySelection'])
        ->name('events.destroy-selection');
    Route::delete('campaigns/selection', [CampaignController::class, 'destroySelection'])
        ->name('campaigns.destroy-selection');

    Route::resource('alumni', AlumniController::class)
        ->parameters(['alumni' => 'alumni']);

    Route::post('alumni/{alumni}/identifiants', [AlumniAccountController::class, 'store'])
        ->name('alumni.credentials.store');
    Route::delete('alumni/{alumni}/identifiants', [AlumniAccountController::class, 'destroy'])
        ->name('alumni.credentials.destroy');
    Route::patch('alumni/{alumni}/acces-portail', [AlumniAccountController::class, 'toggleAccess'])
        ->name('alumni.portal-access');

    Route::resource('events', EventController::class);

    Route::resource('campaigns', CampaignController::class);

    Route::post('campaigns/{campaign}/resend', [CampaignController::class, 'resend'])
        ->name('campaigns.resend');
    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])
        ->name('campaigns.send');

    Route::resource('users', UserController::class)->except(['show']);
    Route::patch('users/{user}/active', [UserController::class, 'toggleActive'])
        ->name('users.toggle-active');

    Route::resource('roles', RoleController::class)->except(['show']);

    Route::get('organisation', [OrganizationSettingsController::class, 'edit'])
        ->name('organization.edit');
    Route::post('organisation', [OrganizationSettingsController::class, 'update'])
        ->name('organization.update');

    Route::get('corbeille', [TrashController::class, 'index'])->name('trash.index');
    Route::patch('corbeille/{type}/{id}', [TrashController::class, 'restore'])
        ->whereNumber('id')
        ->name('trash.restore');
    Route::delete('corbeille/{type}/{id}', [TrashController::class, 'forceDelete'])
        ->whereNumber('id')
        ->name('trash.force-delete');

    Route::post('events/{event}/registrations', [EventRegistrationController::class, 'store'])
        ->name('events.registrations.store');
    Route::delete('events/{event}/registrations/{alumni}', [EventRegistrationController::class, 'destroy'])
        ->name('events.registrations.destroy');
    Route::patch('events/{event}/registrations/{alumni}/attendance', [EventRegistrationController::class, 'attendance'])
        ->name('events.registrations.attendance');
});

Route::middleware(['auth', 'super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::middleware('platform-can:platform.organizations')->group(function () {
            Route::resource('organizations', OrganizationController::class)
                ->except(['show', 'destroy']);
            Route::patch('organizations/{organization}/essai', [OrganizationController::class, 'extendTrial'])
                ->name('organizations.extend-trial');
            Route::patch('organizations/{organization}/active', [OrganizationController::class, 'toggleActive'])
                ->name('organizations.toggle-active');
        });

        Route::resource('plans', PlanController::class)
            ->except(['show'])
            ->middleware('platform-can:platform.plans');

        Route::resource('roles', AdminRoleController::class)->except(['show']);

        Route::middleware('platform-can:platform.subscriptions')->group(function () {
            Route::resource('subscriptions', SubscriptionController::class)
                ->only(['index', 'create', 'store']);
            Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
                ->name('subscriptions.cancel');
        });
    });

require __DIR__.'/portal.php';
require __DIR__.'/settings.php';
