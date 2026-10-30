<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LedgerEntry;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\EscrowLedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ArenaController extends Controller
{
    /**
     * Page 1 — Public Showcase & Discovery (GET /)
     */
    public function index(Request $request, EscrowLedgerService $ledgerService): View|JsonResponse
    {
        $payload = $this->buildArenaPayload($request, $ledgerService);

        if ($request->is('api/*')) {
            return response()->json($payload);
        }

        return view('welcome', $payload);
    }

    /**
     * Page 3 — Authenticated Operator Cockpit & Member Space (GET /dashboard)
     */
    public function dashboard(Request $request, EscrowLedgerService $ledgerService): View|JsonResponse
    {
        $payload = $this->buildArenaPayload($request, $ledgerService);

        $requestedTab = strtolower(trim((string) $request->query('tab', 'overview')));
        if (! in_array($requestedTab, ['overview', 'arenas', 'ledger', 'organizer'], true)) {
            $requestedTab = 'overview';
        }
        $payload['activeTab'] = $requestedTab;

        if ($request->is('api/*')) {
            return response()->json($payload);
        }

        return view('dashboard.index', $payload);
    }

    /**
     * Build shared hydrated state for Public Showcase, Member Cockpit, and /api/state.
     *
     * @return array<string, mixed>
     */
    private function buildArenaPayload(Request $request, EscrowLedgerService $ledgerService): array
    {
        $tournaments = Tournament::with([
            'organizer',
            'registrations.user',
            'firstPlace',
            'secondPlace',
            'thirdPlace',
        ])
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 1 WHEN 'IN_PROGRESS' THEN 2 WHEN 'LOCKED' THEN 3 WHEN 'SETTLED' THEN 4 ELSE 5 END")
            ->orderByDesc('escrow_pool_cents')
            ->get();

        /** @var User|null $currentUser */
        $currentUser = $request->user();
        $wallet = null;
        $ledgerEntries = collect();
        $myRegistrations = collect();

        if ($currentUser !== null) {
            $wallet = $ledgerService->getOrCreateWallet($currentUser);
            $currentUser->load(['registrations.tournament.organizer']);
            $myRegistrations = TournamentRegistration::with(['tournament.organizer', 'tournament.registrations.user'])
                ->where('user_id', $currentUser->id)
                ->orderByDesc('id')
                ->get();
            $ledgerEntries = LedgerEntry::with(['tournament', 'actor'])
                ->where('wallet_id', $wallet->id)
                ->orderByDesc('id')
                ->limit(60)
                ->get();
        } else {
            $ledgerEntries = LedgerEntry::with(['tournament', 'actor', 'wallet.user'])
                ->orderByDesc('id')
                ->limit(25)
                ->get();
        }

        $leaderboard = User::with('wallet')
            ->whereNotIn('email', [
                EscrowLedgerService::ESCROW_VAULT_EMAIL,
                EscrowLedgerService::PLATFORM_TREASURY_EMAIL,
            ])
            ->orderByDesc('elo_rating')
            ->limit(10)
            ->get();

        $demoUsers = User::with('wallet')
            ->whereIn('email', AuthController::ALLOWED_DEMO_EMAILS)
            ->get()
            ->keyBy('email');

        $platformStats = [
            'total_escrow_locked_cents' => (int) Tournament::whereIn('status', [
                Tournament::STATUS_OPEN,
                Tournament::STATUS_LOCKED,
                Tournament::STATUS_IN_PROGRESS,
            ])->sum('escrow_pool_cents'),
            'total_prizes_distributed_cents' => (int) Tournament::where('status', Tournament::STATUS_SETTLED)->sum('net_prize_pool_cents'),
            'total_platform_rake_cents' => (int) Tournament::where('status', Tournament::STATUS_SETTLED)->sum('platform_rake_cents'),
            'active_arenas_count' => (int) Tournament::whereIn('status', [
                Tournament::STATUS_OPEN,
                Tournament::STATUS_LOCKED,
                Tournament::STATUS_IN_PROGRESS,
            ])->count(),
            'settled_arenas_count' => (int) Tournament::where('status', Tournament::STATUS_SETTLED)->count(),
            'rake_bps' => Tournament::DEFAULT_RAKE_BPS,
        ];

        return [
            'tournaments' => $tournaments,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
            'ledgerEntries' => $ledgerEntries,
            'myRegistrations' => $myRegistrations,
            'leaderboard' => $leaderboard,
            'demoUsers' => $demoUsers,
            'platformStats' => $platformStats,
        ];
    }

    public function updateLoadout(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $validated = $request->validate([
            'preferred_class' => ['required', 'string', Rule::in(User::ALLOWED_CLASSES)],
        ]);

        $user->preferred_class = (string) $validated['preferred_class'];
        $user->save();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => "Loadout tactique verrouillé sur {$user->preferred_class}.",
                'user' => $user,
            ]);
        }

        return back()->with('status', "Loadout tactique verrouillé sur {$user->preferred_class}.");
    }
}
