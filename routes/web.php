<?php

declare(strict_types=1);

use App\Http\Controllers\ArenaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Page 1 — Public Arena Showcase & Discovery (GET /)
Route::get('/', [ArenaController::class, 'index'])->name('home');
Route::get('/api/state', [ArenaController::class, 'index'])->name('api.state');

// Page 2 — Dedicated Split-Screen 50/50 Authentication Pages (GET /login & GET /register)
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
});

// Authentication Action Routes (Web + API Dual Endpoints)
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/api/auth/register', [AuthController::class, 'register'])->name('api.auth.register');

    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/api/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

    Route::post('/demo-login', [AuthController::class, 'demoLogin'])->name('auth.demo-login');
    Route::post('/api/auth/demo-login', [AuthController::class, 'demoLogin'])->name('api.auth.demo-login');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/api/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

// Page 3 — Authenticated Operator Cockpit & Member Space (GET /dashboard)
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [ArenaController::class, 'dashboard'])->name('dashboard');
});

// Public Tournament Discovery & Page 4 — Dedicated Tournament Arena Page (GET /tournaments/{tournament})
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::get('/api/tournaments', [TournamentController::class, 'index'])->name('api.tournaments.index');
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::get('/api/tournaments/{tournament}', [TournamentController::class, 'show'])->name('api.tournaments.show');

// Authenticated Financial, Escrow & Tournament Operations
Route::middleware(['auth', 'throttle:financial'])->group(function (): void {
    // Loadout Class Selection
    Route::post('/profile/loadout', [ArenaController::class, 'updateLoadout'])->name('profile.loadout');
    Route::post('/api/profile/loadout', [ArenaController::class, 'updateLoadout'])->name('api.profile.loadout');

    // Wallet & Double-Entry Ledger Inspection + Deposit + SEPA Withdrawal
    Route::get('/wallets/{wallet}', [WalletController::class, 'show'])->name('wallets.show');
    Route::get('/api/wallets/{wallet}', [WalletController::class, 'show'])->name('api.wallets.show');

    Route::post('/wallets/deposit', [WalletController::class, 'deposit'])->name('wallets.deposit');
    Route::post('/api/wallets/deposit', [WalletController::class, 'deposit'])->name('api.wallets.deposit');

    Route::post('/wallets/withdraw', [WalletController::class, 'withdraw'])->name('wallets.withdraw');
    Route::post('/api/wallets/withdraw', [WalletController::class, 'withdraw'])->name('api.wallets.withdraw');

    // Create League / Tournament (ORGANIZER & ADMIN only via TournamentPolicy)
    Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
    Route::post('/api/tournaments', [TournamentController::class, 'store'])->name('api.tournaments.store');

    // Player Buy-In Escrow Registration
    Route::post('/tournaments/{tournament}/register', [TournamentController::class, 'register'])->name('tournaments.register');
    Route::post('/api/tournaments/{tournament}/register', [TournamentController::class, 'register'])->name('api.tournaments.register');

    // Official Tournament Settlement (8% Rake + 60/30/10 Distribution)
    Route::post('/tournaments/{tournament}/settle', [TournamentController::class, 'settle'])->name('tournaments.settle');
    Route::post('/api/tournaments/{tournament}/settle', [TournamentController::class, 'settle'])->name('api.tournaments.settle');

    // Official Tournament Cancellation & 100% Escrow Refund
    Route::post('/tournaments/{tournament}/cancel', [TournamentController::class, 'cancel'])->name('tournaments.cancel');
    Route::post('/api/tournaments/{tournament}/cancel', [TournamentController::class, 'cancel'])->name('api.tournaments.cancel');
});
