<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\AssetSearchController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\HoldingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NetWorthController;
use App\Http\Controllers\PortfolioActionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WealthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('home') : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', fn () => redirect()->route('home'))->name('dashboard');

    Route::get('/net-worth', [NetWorthController::class, 'index'])->name('networth');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/wealth', [WealthController::class, 'index'])->name('wealth');
    Route::get('/discover', [SearchController::class, 'index'])->name('search');

    // Positions
    Route::get('/positions/create', [HoldingController::class, 'create'])->name('holdings.create');
    Route::post('/positions', [HoldingController::class, 'store'])->name('holdings.store');
    Route::get('/positions/{holding}', [HoldingController::class, 'show'])->name('holdings.show');
    Route::get('/positions/{holding}/edit', [HoldingController::class, 'edit'])->name('holdings.edit');
    Route::put('/positions/{holding}', [HoldingController::class, 'update'])->name('holdings.update');
    Route::delete('/positions/{holding}', [HoldingController::class, 'destroy'])->name('holdings.destroy');

    // Accounts
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // Quick actions
    Route::post('/portfolio/refresh', [PortfolioActionController::class, 'refresh'])->name('portfolio.refresh');
    Route::post('/portfolio/visibility', [PortfolioActionController::class, 'toggleVisibility'])->name('portfolio.visibility');

    // JSON endpoints for the charts / search box
    Route::get('/api/series', [ChartController::class, 'series'])->name('api.series');
    Route::get('/api/search', [AssetSearchController::class, 'index'])->name('api.search');

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
