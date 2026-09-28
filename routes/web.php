<?php

use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\Auth\LoginController;
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
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth:web')->name('logout');

// ---- Business admin area ----
Route::middleware(['auth:web', 'business.active', 'role:admin'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
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
