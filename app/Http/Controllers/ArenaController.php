<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ArmoryItem;
use App\Models\Clan;
use App\Models\LedgerEntry;
use App\Models\PaymentRail;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Models\WagerDuel;
use App\Models\Wallet;
use App\Models\WalletFundingRequest;
use App\Services\EscrowLedgerService;
use Closure;
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
        if (! in_array($requestedTab, ['overview', 'arenas', 'duels', 'armory', 'clans', 'ledger', 'organizer'], true)) {
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
        $systemWallets = $ledgerService->ensureSystemWallets();
        $ledgerService->ensureDefaultPaymentRails();
        $ledgerService->ensureDefaultArmoryItems();

        $tournaments = Tournament::with([
            'organizer',
            'registrations.user',
            'firstPlace',
            'secondPlace',
            'thirdPlace',
        ])
            ->orderByDesc('is_featured')
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 1 WHEN 'IN_PROGRESS' THEN 2 WHEN 'LOCKED' THEN 3 WHEN 'SETTLED' THEN 4 ELSE 5 END")
            ->orderByDesc('escrow_pool_cents')
            ->get();

        $paymentRails = PaymentRail::where('is_active', true)
            ->orderBy('id')
            ->get();

        $wagerDuels = WagerDuel::with(['challenger', 'opponent', 'winner'])
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 1 WHEN 'IN_PROGRESS' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $armoryItems = ArmoryItem::where('is_active', true)
            ->orderBy('price_cents')
            ->get();

        $clans = Clan::with('owner')
            ->orderByDesc('member_count')
            ->orderBy('id')
            ->get();

        /** @var User|null $currentUser */
        $currentUser = $request->user();
        $wallet = null;
        $ledgerEntries = collect();
        $myRegistrations = collect();
        $myPaymentMethods = collect();
        $myFundingRequests = collect();
        $myArmoryItemIds = [];
        $myPredictions = collect();
        $myDisputes = collect();

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
            $myPaymentMethods = UserPaymentMethod::with('paymentRail')
                ->where('user_id', $currentUser->id)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get();
            $myFundingRequests = WalletFundingRequest::with('paymentRail')
                ->where('user_id', $currentUser->id)
                ->orderByDesc('id')
                ->limit(25)
                ->get();
            $myArmoryItemIds = $currentUser->armoryPurchases()
                ->pluck('armory_item_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $myPredictions = $currentUser->predictions()
                ->with(['tournament', 'predictedWinner'])
                ->orderByDesc('id')
                ->get();
            $myDisputes = $currentUser->disputes()
                ->with('tournament')
                ->orderByDesc('id')
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
            'total_organizer_revshare_cents' => (int) Tournament::where('status', Tournament::STATUS_SETTLED)->sum('organizer_rake_cents'),
            'treasury_balance_cents' => (int) $systemWallets['treasury']->balance_cents,
            'active_arenas_count' => (int) Tournament::whereIn('status', [
                Tournament::STATUS_OPEN,
                Tournament::STATUS_LOCKED,
                Tournament::STATUS_IN_PROGRESS,
            ])->count(),
            'settled_arenas_count' => (int) Tournament::where('status', Tournament::STATUS_SETTLED)->count(),
            'open_duels_count' => (int) WagerDuel::where('status', WagerDuel::STATUS_OPEN)->count(),
            'rake_bps' => Tournament::DEFAULT_RAKE_BPS,
            'prime_price_cents' => EscrowLedgerService::PRIME_SUBSCRIPTION_PRICE_CENTS,
            'boost_price_cents' => EscrowLedgerService::ARENA_BOOST_PRICE_CENTS,
            'clan_license_price_cents' => EscrowLedgerService::CLAN_LICENSE_PRICE_CENTS,
            'dispute_bond_cents' => EscrowLedgerService::DISPUTE_BOND_CENTS,
        ];

        return [
            'tournaments' => $tournaments,
            'paymentRails' => $paymentRails,
            'wagerDuels' => $wagerDuels,
            'armoryItems' => $armoryItems,
            'clans' => $clans,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
            'ledgerEntries' => $ledgerEntries,
            'myRegistrations' => $myRegistrations,
            'myPaymentMethods' => $myPaymentMethods,
            'myFundingRequests' => $myFundingRequests,
            'myArmoryItemIds' => $myArmoryItemIds,
            'myPredictions' => $myPredictions,
            'myDisputes' => $myDisputes,
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

    /**
     * Brick 1 — Create an Instant 1v1 Wager Duel (POST /duels & POST /api/duels)
     */
    public function storeDuel(Request $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $challenger */
        $challenger = $request->user();

        $validated = $request->validate([
            'game' => ['required', 'string', 'min:3', 'max:80'],
            'loadout_class' => ['nullable', 'string', Rule::in(User::ALLOWED_CLASSES)],
            'stake_cents' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_float($value) || is_bool($value) || ! preg_match('/^-?\d+$/', (string) $value)) {
                        $fail('La mise du duel doit être exprimée en centimes entiers.');
                    }
                },
                'integer',
                'min:500',
                'max:5000000',
            ],
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->createWagerDuel(
            challenger: $challenger,
            game: trim((string) $validated['game']),
            loadoutClass: isset($validated['loadout_class']) && $validated['loadout_class'] !== null
                ? (string) $validated['loadout_class']
                : ($challenger->preferred_class ?: 'VANGUARD'),
            stakeCents: (int) $validated['stake_cents'],
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $formatted = Wallet::formatCents((int) $validated['stake_cents']);
        $message = $result['idempotent_replay']
            ? "Duel 1v1 {$result['duel']->code} déjà ouvert (réponse idempotente)."
            : "Défi 1v1 « {$result['duel']->code} » ouvert ({$formatted} verrouillés en séquestre).";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'duel' => $result['duel'],
                'wallet' => $result['wallet'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], $result['idempotent_replay'] ? 200 : 201);
        }

        return back()->with('status', $message);
    }

    /**
     * Brick 1 — Accept an Open 1v1 Wager Duel (POST /duels/{duel}/accept & POST /api/duels/{duel}/accept)
     */
    public function acceptDuel(Request $request, WagerDuel $duel, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $opponent */
        $opponent = $request->user();

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->acceptWagerDuel(
            opponent: $opponent,
            duel: $duel,
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $potFormatted = Wallet::formatCents((int) $result['duel']->escrow_pot_cents);
        $message = $result['idempotent_replay']
            ? "Duel 1v1 {$result['duel']->code} déjà accepté (réponse idempotente)."
            : "Défi 1v1 « {$result['duel']->code} » accepté — Pot séquestré de {$potFormatted} verrouillé (EN COURS).";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'duel' => $result['duel'],
                'wallet' => $result['wallet'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    /**
     * Brick 1 — Settle an In-Progress 1v1 Wager Duel (POST /duels/{duel}/settle & POST /api/duels/{duel}/settle)
     */
    public function settleDuel(Request $request, WagerDuel $duel, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $isParticipant = (int) $actor->id === (int) $duel->challenger_id
            || (int) $actor->id === (int) $duel->opponent_id;
        if (! $actor->isAdmin() && ! $actor->isOrganizer() && ! $isParticipant) {
            abort(403, 'Vous n\'êtes pas autorisé à clôturer ce duel 1v1.');
        }

        $validated = $request->validate([
            'winner_user_id' => ['required', 'integer', 'exists:users,id'],
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->settleWagerDuel(
            actor: $actor,
            duel: $duel,
            winnerUserId: (int) $validated['winner_user_id'],
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $payoutFormatted = Wallet::formatCents((int) $result['duel']->winner_payout_cents);
        $message = $result['idempotent_replay']
            ? "Duel 1v1 {$result['duel']->code} déjà clôturé (réponse idempotente)."
            : "Duel 1v1 « {$result['duel']->code} » clôturé : {$payoutFormatted} (92 %) versés au vainqueur, 8 % à SYS_TREASURY.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'duel' => $result['duel'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    /**
     * Brick 2 — Buy an Armory Item (POST /armory/{armoryItem}/buy & POST /api/armory/{armoryItem}/buy)
     */
    public function buyArmoryItem(
        Request $request,
        ArmoryItem $armoryItem,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        /** @var User $buyer */
        $buyer = $request->user();

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->purchaseArmoryItem(
            buyer: $buyer,
            item: $armoryItem,
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $priceFormatted = Wallet::formatCents((int) $armoryItem->price_cents);
        $message = $result['idempotent_replay']
            ? "Licence « {$armoryItem->name} » déjà équipée (réponse idempotente)."
            : "Licence « {$armoryItem->name} » acquise ({$priceFormatted}) et badge « {$armoryItem->badge_label} » équipé !";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'purchase' => $result['purchase'],
                'user' => $result['user'],
                'wallet' => $result['wallet'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    /**
     * Brick 4 — Create a B2B Clan Franchise (POST /clans & POST /api/clans)
     */
    public function storeClan(Request $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $founder */
        $founder = $request->user();

        $validated = $request->validate([
            'tag' => ['required', 'string', 'min:2', 'max:8'],
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->createClan(
            founder: $founder,
            tag: (string) $validated['tag'],
            name: (string) $validated['name'],
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $message = $result['idempotent_replay']
            ? "Franchise [{$result['clan']->tag}] déjà créée (réponse idempotente)."
            : "Franchise « [{$result['clan']->tag}] {$result['clan']->name} » fondée (29,00 € de licence B2B versés à SYS_TREASURY).";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'clan' => $result['clan'],
                'user' => $result['user'],
                'wallet' => $result['wallet'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], $result['idempotent_replay'] ? 200 : 201);
        }

        return back()->with('status', $message);
    }

    /**
     * Brick 4 — Join an Existing Clan (POST /clans/{clan}/join & POST /api/clans/{clan}/join)
     */
    public function joinClan(Request $request, Clan $clan, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatedClan = $ledgerService->joinClan(
            user: $user,
            clan: $clan,
        );

        $message = "Vous avez rejoint l'escouade « [{$updatedClan->tag}] {$updatedClan->name} ».";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'clan' => $updatedClan,
                'user' => $user->fresh(),
            ], 200);
        }

        return back()->with('status', $message);
    }
}
