<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\FreeAgentOAuthController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\PortalReportController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\ReportPreviewController;
use App\Http\Controllers\XeroOAuthController;
use App\Livewire\Activity;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Branding;
use App\Livewire\Clients;
use App\Livewire\Dashboard;
use App\Livewire\Install;
use App\Livewire\Integrations;
use App\Livewire\Portal;
use App\Livewire\Reports;
use App\Livewire\Settings;
use App\Livewire\Sites;
use App\Livewire\Templates;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::get('/install', Install\Wizard::class)->name('install');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/two-factor-challenge', TwoFactorChallenge::class)->name('two-factor.challenge');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Each user manages their own two-factor setup, regardless of role.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/settings/two-factor', Settings\TwoFactor::class)->name('settings.two-factor');
});

/*
| Public report share links. No authentication — access is gated by the
| unguessable token plus optional expiry, revocation and password.
*/
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/r/{token}', [PublicReportController::class, 'show'])->name('public-report');
    Route::post('/r/{token}/unlock', [PublicReportController::class, 'unlock'])
        ->middleware('throttle:10,1')->name('public-report.unlock');
});

/*
| Client portal. A restricted, agency-branded area where a client sees only
| their own sites and generated reports.
*/
Route::middleware(['auth', 'active', 'can:access-portal'])->prefix('portal')->group(function () {
    Route::get('/', Portal\Dashboard::class)->name('portal.dashboard');
    Route::get('/reports/{report}', PortalReportController::class)->name('portal.report');
});

/*
| Agency administration. Requires an authenticated, active staff account.
| Client-portal users (role: client) are handled separately and do not pass
| the access-admin gate.
*/
Route::middleware(['auth', 'active', 'can:access-admin'])->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Clients
    Route::get('/clients', Clients\Index::class)->name('clients.index');
    Route::get('/clients/create', Clients\Form::class)->middleware('can:manage-clients')->name('clients.create');
    Route::get('/clients/{client}', Clients\Show::class)->name('clients.show');
    Route::get('/clients/{client}/edit', Clients\Form::class)->middleware('can:manage-clients')->name('clients.edit');

    Route::get('/clients/{client}/branding', Branding\Manage::class)->middleware('can:manage-clients')->name('clients.branding');

    // Sites
    Route::get('/sites', Sites\Index::class)->name('sites.index');
    Route::get('/sites/import', Sites\Import::class)->middleware('can:manage-sites')->name('sites.import');
    Route::get('/sites/create', Sites\Form::class)->middleware('can:manage-sites')->name('sites.create');
    Route::get('/sites/{site}', Sites\Show::class)->name('sites.show');
    Route::get('/sites/{site}/edit', Sites\Form::class)->middleware('can:manage-sites')->name('sites.edit');
    Route::get('/sites/{site}/branding', Branding\Manage::class)->middleware('can:manage-sites')->name('sites.branding');

    // Reports
    Route::get('/reports', Reports\Index::class)->name('reports.index');
    Route::get('/reports/create', Reports\Create::class)->middleware('can:manage-reports')->name('reports.create');
    Route::get('/reports/{report}', Reports\Show::class)->name('reports.show');
    Route::get('/reports/{report}/preview', ReportPreviewController::class)->name('reports.preview');
    Route::get('/reports/{report}/pdf', ReportPdfController::class)->name('reports.pdf');
    Route::get('/reports/{report}/edit', Reports\Builder::class)->middleware('can:manage-reports')->name('reports.edit');

    // Reusable report templates
    Route::middleware('can:manage-reports')->group(function () {
        Route::get('/templates', Templates\Index::class)->name('templates.index');
        Route::get('/templates/create', Templates\Form::class)->name('templates.create');
        Route::get('/templates/{template}/edit', Templates\Form::class)->name('templates.edit');
    });

    // Background collection activity
    Route::get('/activity', Activity\Index::class)->middleware('can:manage-integrations')->name('activity.index');

    // Integrations
    Route::get('/integrations', Integrations\Catalog::class)->name('integrations.index');
    Route::middleware('can:manage-integrations')->group(function () {
        Route::get('/sites/{site}/integrations/connect/{key}', Integrations\Setup::class)->name('sites.integrations.connect');
        Route::get('/integrations/workspace/connect/{key}', Integrations\WorkspaceSetup::class)->name('integrations.workspace.connect');
        Route::get('/integrations/workspace/{workspace}/manage', Integrations\WorkspaceSetup::class)->name('integrations.workspace.edit');
        Route::get('/integrations/{connection}/manage', Integrations\Setup::class)->name('integrations.edit');
        Route::get('/integrations/{connection}/google/connect', [GoogleOAuthController::class, 'redirect'])->name('integrations.google.connect');
        Route::get('/integrations/workspace/{workspace}/google/connect', [GoogleOAuthController::class, 'redirectWorkspace'])->name('integrations.workspace.google.connect');
        Route::get('/integrations/google/callback', [GoogleOAuthController::class, 'callback'])->name('integrations.google.callback');
        Route::get('/integrations/workspace/{workspace}/freeagent/connect', [FreeAgentOAuthController::class, 'redirect'])->name('integrations.workspace.freeagent.connect');
        Route::get('/integrations/freeagent/callback', [FreeAgentOAuthController::class, 'callback'])->name('integrations.freeagent.callback');
        Route::get('/integrations/workspace/{workspace}/xero/connect', [XeroOAuthController::class, 'redirect'])->name('integrations.workspace.xero.connect');
        Route::get('/integrations/xero/callback', [XeroOAuthController::class, 'callback'])->name('integrations.xero.callback');
    });

    // Global agency branding
    Route::get('/branding', Branding\Manage::class)->middleware('can:manage-branding')->name('branding.edit');

    // Application settings
    Route::get('/settings', Settings\Manage::class)->middleware('can:manage-settings')->name('settings.edit');
    Route::get('/settings/ai', Settings\Ai::class)->middleware('can:manage-settings')->name('settings.ai');

    Route::middleware('can:manage-users')->group(function () {
        Route::get('/users', Users\Index::class)->name('users.index');
        Route::get('/users/create', Users\Form::class)->name('users.create');
        Route::get('/users/{user}/edit', Users\Form::class)->name('users.edit');
    });
});
