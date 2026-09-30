<?php

use App\Http\Controllers\App\AbsenceController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DocumentController;
use App\Http\Controllers\App\EmployeeController;
use App\Http\Controllers\App\ReportTaskController;
use App\Http\Controllers\App\RequestController;
use App\Http\Controllers\App\RetentionController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Ops\BusinessController;
use App\Http\Controllers\Ops\EnquiryController;
use App\Http\Controllers\Ops\OpsAuthController;
use App\Http\Controllers\Ops\PricingController;
use App\Http\Controllers\Billing\PayPalWebhookController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Ops\GatewayController;
use App\Http\Controllers\Website\SignupController;
use App\Http\Controllers\Website\WebsiteController;
use App\Http\Controllers\Portal\DetailsController;
use App\Http\Controllers\Portal\DocumentController as PortalDocumentController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\Portal\LeaveController;
use App\Http\Controllers\Portal\RequestController as PortalRequestController;
use Illuminate\Support\Facades\Route;

// ---- Public website ----
Route::get('/', [WebsiteController::class, 'home'])->name('home');
Route::post('/contact', [WebsiteController::class, 'contact'])->middleware('throttle:5,10')->name('contact');
// Sign-up and payment (Stripe Checkout). The webhook is signed by Stripe, not CSRF-protected.
Route::middleware('stripe')->group(function () {
    Route::get('/signup', [SignupController::class, 'show'])->name('signup');
    Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:10,10')->name('signup.store');
    Route::get('/signup/done', [SignupController::class, 'done'])->middleware('throttle:30,1')->name('signup.done');
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('stripe.webhook');
});
Route::get('/signup/paypal/done', [SignupController::class, 'paypalDone'])->middleware('throttle:30,1')->name('signup.paypal.done');
Route::post('/paypal/webhook', PayPalWebhookController::class)->middleware('throttle:120,1')->name('paypal.webhook');
Route::get('/privacy', [WebsiteController::class, 'legal'])->defaults('page', 'privacy')->name('privacy');
Route::get('/terms', [WebsiteController::class, 'legal'])->defaults('page', 'terms')->name('terms');

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
    Route::get('/employees/{employee}/compliance-pack', [EmployeeController::class, 'pack'])->whereNumber('employee')->middleware('throttle:20,1')->name('employees.pack');

    // Retention: leavers' records due for deletion (reviewed and confirmed by the admin).
    Route::get('/retention', [RetentionController::class, 'index'])->name('retention.index');
    Route::delete('/retention/{employee}', [RetentionController::class, 'destroy'])->whereNumber('employee')->name('retention.destroy');

    // Requests from the employee portal (compliance-rules §6).
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('/requests/{employeeRequest}/approve', [RequestController::class, 'approve'])->whereNumber('employeeRequest')->name('requests.approve');
    Route::post('/requests/{employeeRequest}/decline', [RequestController::class, 'decline'])->whereNumber('employeeRequest')->name('requests.decline');

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
    Route::post('/settings/billing', [SettingsController::class, 'billing'])->middleware(['stripe', 'throttle:10,1'])->name('billing');
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
    Route::get('/documents', [PortalDocumentController::class, 'index'])->name('documents');
    Route::post('/documents', [PortalDocumentController::class, 'store'])->middleware('throttle:20,1')->name('documents.store');
    Route::get('/documents/{document}', [PortalDocumentController::class, 'show'])->whereNumber('document')->name('documents.show');
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave');
    Route::get('/leave/check', [LeaveController::class, 'check'])->middleware('throttle:120,1')->name('leave.check');
    Route::post('/leave', [LeaveController::class, 'store'])->middleware('throttle:20,1')->name('leave.store');
    Route::get('/update-details', [DetailsController::class, 'editChange'])->name('change');
    Route::post('/update-details', [DetailsController::class, 'storeChange'])->middleware('throttle:20,1')->name('change.store');
    Route::get('/requests', PortalRequestController::class)->name('requests');
    Route::get('/details', [DetailsController::class, 'show'])->name('details');
    Route::post('/security/two-factor', [DetailsController::class, 'startTwoFactor'])->name('two-factor.start');
    Route::post('/security/two-factor/confirm', [DetailsController::class, 'confirmTwoFactor'])->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::delete('/security/two-factor', [DetailsController::class, 'disableTwoFactor'])->middleware('throttle:10,1')->name('two-factor.disable');
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
        Route::get('/pricing', [PricingController::class, 'show'])->name('pricing');
        Route::put('/pricing', [PricingController::class, 'update'])->name('pricing.update');
        Route::get('/gateways', [GatewayController::class, 'show'])->name('gateways');
        Route::put('/gateways/stripe', [GatewayController::class, 'updateStripe'])->middleware('throttle:10,1')->name('gateways.stripe');
        Route::put('/gateways/paypal', [GatewayController::class, 'updatePaypal'])->middleware('throttle:10,1')->name('gateways.paypal');
        Route::post('/pricing/move', [PricingController::class, 'move'])->middleware('throttle:5,1')->name('pricing.move');
        Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries');
        Route::post('/enquiries/{enquiry}/handled', [EnquiryController::class, 'handle'])->whereNumber('enquiry')->name('enquiries.handle');
        Route::post('/logout', [OpsAuthController::class, 'destroy'])->name('logout');
    });
});
