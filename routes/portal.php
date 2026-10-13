<?php

use App\Http\Controllers\Portal\AssistantController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EventController;
use App\Http\Controllers\Portal\ForgotPasswordController;
use App\Http\Controllers\Portal\JobController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\MessageController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\PasswordController;
use App\Http\Controllers\Portal\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('portail')->name('portal.')->group(function () {
    Route::middleware('guest:alumni')
        ->prefix('{organization:slug}')
        ->group(function () {
            Route::get('connexion', [LoginController::class, 'create'])->name('login');
            Route::post('connexion', [LoginController::class, 'store'])->name('login.store');

            Route::get('mot-de-passe-oublie', [ForgotPasswordController::class, 'create'])
                ->name('password.request');
            Route::post('mot-de-passe-oublie', [ForgotPasswordController::class, 'store'])
                ->name('password.email');
            Route::get('reinitialiser/{token}', [ForgotPasswordController::class, 'edit'])
                ->name('password.reset');
            Route::post('reinitialiser/{token}', [ForgotPasswordController::class, 'update'])
                ->name('password.update');
        });

    Route::middleware(['auth:alumni', 'alumni'])->group(function () {
        Route::post('deconnexion', [LoginController::class, 'destroy'])->name('logout');

        Route::get('mot-de-passe', [PasswordController::class, 'edit'])->name('password.change');
        Route::post('mot-de-passe', [PasswordController::class, 'update'])->name('password.change.store');

        Route::middleware('alumni-password')->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');

            Route::middleware('portal-can:portal.events.view')->group(function () {
                Route::get('evenements', [EventController::class, 'index'])->name('events.index');
                Route::get('evenements/{event}', [EventController::class, 'show'])->name('events.show');
            });
            Route::middleware('portal-can:portal.events.register')->group(function () {
                Route::post('evenements/{event}/inscription', [EventController::class, 'register'])
                    ->name('events.register');
                Route::delete('evenements/{event}/inscription', [EventController::class, 'cancel'])
                    ->name('events.cancel');
            });

            Route::middleware('portal-can:portal.messages.view')->group(function () {
                Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
                Route::post('messages/tout-lire', [MessageController::class, 'readAll'])
                    ->name('messages.read-all');
                Route::get('messages/{campaign}', [MessageController::class, 'show'])
                    ->name('messages.show');
            });

            Route::middleware('portal-can:portal.notifications.view')->group(function () {
                Route::get('notifications', [NotificationController::class, 'index'])
                    ->name('notifications.index');
                Route::post('notifications/tout-lire', [NotificationController::class, 'readAll'])
                    ->name('notifications.read-all');
                Route::post('notifications/{notification}', [NotificationController::class, 'update'])
                    ->name('notifications.read');
            });

            Route::middleware('portal-can:portal.assistant.use')->group(function () {
                Route::get('assistant', [AssistantController::class, 'index'])
                    ->name('assistant.index');
                Route::get('assistant/{conversation}', [AssistantController::class, 'index'])
                    ->whereNumber('conversation')
                    ->name('assistant.show');
                Route::post('assistant', [AssistantController::class, 'store'])
                    ->name('assistant.store');
                Route::delete('assistant/{conversation}', [AssistantController::class, 'destroy'])
                    ->whereNumber('conversation')
                    ->name('assistant.destroy');
            });

            Route::middleware('portal-can:portal.jobs.view')->group(function () {
                Route::get('offres', [JobController::class, 'index'])->name('jobs.index');
                Route::get('candidatures', [JobController::class, 'applications'])
                    ->name('jobs.applications');
            });
            Route::middleware('portal-can:portal.jobs.apply')->group(function () {
                Route::post('offres', [JobController::class, 'store'])->name('jobs.store');
                Route::delete('candidatures/{application}', [JobController::class, 'destroy'])
                    ->name('jobs.destroy');
            });

            Route::middleware('portal-can:portal.profile.update')->group(function () {
                Route::get('parametres/profil', [ProfileController::class, 'edit'])
                    ->name('settings.profile');
                Route::put('parametres/profil', [ProfileController::class, 'update'])
                    ->name('settings.profile.update');
            });

            Route::get('parametres/mot-de-passe', [PasswordController::class, 'editSettings'])
                ->name('settings.password');
            Route::put('parametres/mot-de-passe', [PasswordController::class, 'updateSettings'])
                ->name('settings.password.update');
        });
    });
});
