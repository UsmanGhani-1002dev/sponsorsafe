<?php

use App\Http\Controllers\App\AbsenceController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DocumentController;
use App\Http\Controllers\App\EmployeeController;
use App\Http\Controllers\App\ReportTaskController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Ops\BusinessController;
use App\Http\Controllers\Ops\OpsAuthController;
use App\Http\Controllers\Portal\HomeController;
use Illuminate\Support\Facades\Route;

// Public website arrives in Stage 7; until then the root goes to sign-in.
Route::redirect('/', '/login');

// ---- Sign-in for business admins and employees (email first, then password) ----
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login/lookup', [LoginController::class, 'lookup'])->name('login.lookup');
    Route::post('/login/reset', [LoginController::class, 'reset'])->name('login.reset');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/login/forgot', [LoginController::class, 'forgot'])->name('login.forgot');

    // Authenticator code: required for business admins, optional for employees.
    Route::get('/login/verify', [TwoFactorController::class, 'show'])->name('login.two-factor');
    Route::post('/login/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('login.two-factor.verify');

    // Link from a portal invite (7 days) or password reset (60 minutes); single-use.
    Route::get('/set-password/{token}', [SetPasswordController::class, 'show'])->name('password.set');
    Route::post('/set-password/{token}', [SetPasswordController::class, 'store'])->middleware('throttle:10,1')->name('password.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth:web')->name('logout');

// ---- Business admin area ----
Route::middleware(['auth:web', 'business.active', 'role:admin'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
    Route::put('/employees/{employee}/personal', [EmployeeController::class, 'correct'])->whereNumber('employee')->name('employees.correct');
    Route::post('/employees/{employee}/changes', [EmployeeController::class, 'recordChange'])->whereNumber('employee')->name('employees.changes');
    Route::post('/employees/{employee}/invite', [EmployeeController::class, 'invite'])->whereNumber('employee')->name('employees.invite');
    Route::post('/employees/{employee}/end', [EmployeeController::class, 'end'])->whereNumber('employee')->name('employees.end');

    // Home Office reports (compliance-rules §4).
    Route::get('/reports', [ReportTaskController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportTaskController::class, 'store'])->name('reports.store');
    Route::post('/reports/{task}/reported', [ReportTaskController::class, 'reported'])->whereNumber('task')->name('reports.reported');
    Route::post('/reports/{task}/not-required', [ReportTaskController::class, 'notRequired'])->whereNumber('task')->name('reports.not-required');
    Route::post('/reports/{task}/reopen', [ReportTaskController::class, 'reopen'])->whereNumber('task')->name('reports.reopen');

    // Documents: private files, every view and download audited.
    Route::post('/employees/{employee}/documents', [DocumentController::class, 'store'])->whereNumber('employee')->name('documents.store');
    Route::post('/employees/{employee}/document-requests', [DocumentController::class, 'requestFromEmployee'])->whereNumber('employee')->name('documents.request');
    Route::post('/document-requests/{documentRequest}/cancel', [DocumentController::class, 'cancelRequest'])->whereNumber('documentRequest')->name('documents.request.cancel');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->whereNumber('document')->name('documents.show');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->whereNumber('document')->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->whereNumber('document')->name('documents.destroy');

    // Absence (compliance-rules §3).
    Route::get('/absence', [AbsenceController::class, 'index'])->name('absence.index');
    Route::get('/absence/create', [AbsenceController::class, 'create'])->name('absence.create');
    Route::get('/absence/check', [AbsenceController::class, 'check'])->middleware('throttle:120,1')->name('absence.check');
    Route::post('/absence', [AbsenceController::class, 'store'])->name('absence.store');
    Route::get('/absence/export.csv', [AbsenceController::class, 'exportCsv'])->name('absence.export.csv');
    Route::get('/absence/export.pdf', [AbsenceController::class, 'exportPdf'])->name('absence.export.pdf');
    Route::delete('/absence/{absence}', [AbsenceController::class, 'destroy'])->whereNumber('absence')->name('absence.destroy');
    Route::post('/absence/{absence}/fit-note', [AbsenceController::class, 'fitNote'])->whereNumber('absence')->name('absence.fit-note');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/rules', [SettingsController::class, 'updateRules'])->name('rules.update');
    Route::post('/settings/people', [SettingsController::class, 'storePerson'])->name('people.store');
    Route::put('/settings/people/{person}', [SettingsController::class, 'updatePerson'])->whereNumber('person')->name('people.update');
    Route::delete('/settings/people/{person}', [SettingsController::class, 'destroyPerson'])->whereNumber('person')->name('people.destroy');
    Route::post('/settings/sites', [SettingsController::class, 'storeSite'])->name('sites.store');
    Route::put('/settings/sites/{site}', [SettingsController::class, 'updateSite'])->whereNumber('site')->name('sites.update');
    Route::post('/settings/sites/{site}/close', [SettingsController::class, 'closeSite'])->whereNumber('site')->name('sites.close');
    Route::post('/settings/move', [SettingsController::class, 'moveEmployee'])->name('sites.move');
});

// ---- Employee portal ----
Route::middleware(['auth:web', 'business.active', 'role:employee'])->prefix('me')->name('portal.')->group(function () {
    Route::get('/', HomeController::class)->name('home');
});

// ---- Super admin: secret path, IP allow-list, password + authenticator code ----
Route::prefix(config('sponsorsafe.ops_path'))->middleware('ops.ip')->name('ops.')->group(function () {
    Route::get('/login', [OpsAuthController::class, 'show'])->name('login');
    Route::post('/login', [OpsAuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/verify', [OpsAuthController::class, 'twoFactor'])->name('two-factor');
    Route::post('/verify', [OpsAuthController::class, 'verify'])->middleware('throttle:10,1')->name('two-factor.verify');

    Route::middleware('ops.auth')->group(function () {
        Route::get('/', [BusinessController::class, 'index'])->name('businesses');
        Route::post('/businesses/{business}/toggle', [BusinessController::class, 'toggle'])->name('businesses.toggle');
        Route::post('/logout', [OpsAuthController::class, 'destroy'])->name('logout');
    });
});
