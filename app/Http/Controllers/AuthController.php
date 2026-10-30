<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\EscrowLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public const array ALLOWED_DEMO_EMAILS = [
        'admin@valkyr.arena',
        'organizer@valkyr.arena',
        'rival.org@valkyr.arena',
        'nyx@valkyr.arena',
        'kestrel@valkyr.arena',
        'aether@valkyr.arena',
        'sovereign@valkyr.arena',
    ];

    public function register(RegisterRequest $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Force role to PLAYER and default ELO to 1500 regardless of any extra fields sent in payload (Anti-Mass Assignment)
        $user = User::create([
            'name' => (string) $validated['name'],
            'gamertag' => strtoupper(trim((string) $validated['gamertag'])),
            'email' => strtolower(trim((string) $validated['email'])),
            'password' => (string) $validated['password'],
            'role' => User::ROLE_PLAYER,
            'elo_rating' => 1500,
            'preferred_class' => isset($validated['preferred_class']) ? (string) $validated['preferred_class'] : 'VANGUARD',
        ]);

        $wallet = $ledgerService->getOrCreateWallet($user);

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Opérateur enregistré et portefeuille séquestre initialisé.',
                'user' => $user->load('wallet'),
                'wallet' => $wallet,
            ], 201);
        }

        return redirect()->route('home')->with('status', 'Bienvenue dans VALKYR // ARENA, '.$user->gamertag.'.');
    }

    public function login(LoginRequest $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        $credentials = [
            'email' => strtolower(trim((string) $request->validated('email'))),
            'password' => (string) $request->validated('password'),
        ];

        // Block direct login to internal system escrow/treasury accounts
        if (in_array($credentials['email'], [
            EscrowLedgerService::ESCROW_VAULT_EMAIL,
            EscrowLedgerService::PLATFORM_TREASURY_EMAIL,
        ], true)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Identifiants tactiques invalides.',
                    'errors' => ['email' => ['Identifiants tactiques invalides.']],
                ], 422);
            }

            return back()->withErrors(['email' => 'Identifiants tactiques invalides.']);
        }

        if (! Auth::attempt($credentials)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Identifiants tactiques invalides.',
                    'errors' => ['email' => ['Identifiants tactiques invalides.']],
                ], 422);
            }

            return back()->withErrors(['email' => 'Identifiants tactiques invalides.']);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = Auth::user();
        $wallet = $ledgerService->getOrCreateWallet($user);

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Connexion tactique établie.',
                'user' => $user->load('wallet'),
                'wallet' => $wallet,
            ]);
        }

        return redirect()->route('home')->with('status', 'Session active : '.$user->gamertag);
    }

    public function demoLogin(Request $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        if (! in_array($email, self::ALLOWED_DEMO_EMAILS, true)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Compte de démonstration non autorisé.',
                ], 403);
            }

            return back()->with('error', 'Compte de démonstration non autorisé.');
        }

        $user = User::where('email', $email)->first();
        if ($user === null) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Compte de démonstration introuvable.',
                ], 404);
            }

            return back()->with('error', 'Compte de démonstration introuvable.');
        }

        $wallet = $ledgerService->getOrCreateWallet($user);

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => "Basculé sur le profil démo {$user->gamertag} ({$user->role}).",
                'user' => $user->load('wallet'),
                'wallet' => $wallet,
            ]);
        }

        return redirect()->route('home')->with('status', "Profil actif : {$user->gamertag} [{$user->role}]");
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Session déconnectée.',
            ]);
        }

        return redirect()->route('home')->with('status', 'Session déconnectée.');
    }
}
