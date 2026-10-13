<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\AlumniAccountController;
use App\Http\Controllers\AlumniController;
use App\Http\Controllers\AlumniImportController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route(Auth::user()->isSuperAdmin() ? 'admin.dashboard' : 'dashboard')
        : redirect()->route('login');
})->name('home');
Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

Route::middleware(['auth', 'organization'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

        Route::resource('organizations', OrganizationController::class)
            ->except(['show', 'destroy']);
        Route::patch('organizations/{organization}/active', [OrganizationController::class, 'toggleActive'])
            ->name('organizations.toggle-active');

        Route::resource('plans', PlanController::class)->except(['show']);

        Route::resource('subscriptions', SubscriptionController::class)
            ->only(['index', 'create', 'store']);
        Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
            ->name('subscriptions.cancel');
    });

require __DIR__.'/portal.php';
require __DIR__.'/settings.php';
