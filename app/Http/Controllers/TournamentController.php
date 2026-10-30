<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelTournamentRequest;
use App\Http\Requests\RegisterTournamentRequest;
use App\Http\Requests\SettleTournamentRequest;
use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\EscrowLedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tournament::with([
            'organizer',
            'registrations.user',
            'firstPlace',
            'secondPlace',
            'thirdPlace',
        ]);

        if ($request->filled('game') && $request->string('game')->toString() !== 'ALL') {
            $query->where('game', $request->string('game')->toString());
        }

        if ($request->filled('status') && $request->string('status')->toString() !== 'ALL') {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('format') && $request->string('format')->toString() !== 'ALL') {
            $query->where('format', $request->string('format')->toString());
        }

        $minBuyIn = $request->input('min_buy_in_cents', $request->input('min_Buy_in_cents'));
        if ($minBuyIn !== null && $minBuyIn !== '') {
            $query->where('entry_fee_cents', '>=', (int) $minBuyIn);
        }

        if ($request->filled('max_buy_in_cents')) {
            $query->where('entry_fee_cents', '<=', (int) $request->input('max_buy_in_cents'));
        }

        if ($request->filled('min_prize_cents')) {
            $query->where('escrow_pool_cents', '>=', (int) $request->input('min_prize_cents'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('game', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $tournaments = $query
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 1 WHEN 'IN_PROGRESS' THEN 2 WHEN 'LOCKED' THEN 3 WHEN 'SETTLED' THEN 4 ELSE 5 END")
            ->orderByDesc('escrow_pool_cents')
            ->get();

        return response()->json([
            'tournaments' => $tournaments,
            'total_count' => $tournaments->count(),
            'active_count' => $tournaments->whereIn('status', [
                Tournament::STATUS_OPEN,
                Tournament::STATUS_LOCKED,
                Tournament::STATUS_IN_PROGRESS,
            ])->count(),
        ]);
    }

    /**
     * Page 4 — Dedicated Tournament Arena Detail Page (GET /tournaments/{tournament:slug})
     * Also returns structured JSON when called via /api/tournaments/{tournament} or expectsJson().
     */
    public function show(Request $request, Tournament $tournament, EscrowLedgerService $ledgerService): View|JsonResponse
    {
        $tournament->load([
            'organizer',
            'registrations.user.wallet',
            'firstPlace',
            'secondPlace',
            'thirdPlace',
            'ledgerEntries.actor',
            'ledgerEntries.wallet.user',
        ]);

        // Calculate exact server-side integer math breakdown (8% Rake + 60/30/10 Net Pool)
        $grossPoolCents = (int) $tournament->escrow_pool_cents;
        $referencePoolCents = $grossPoolCents > 0
            ? $grossPoolCents
            : (int) $tournament->entry_fee_cents * (int) $tournament->max_participants;

        $rakeBps = (int) ($tournament->rake_bps ?: Tournament::DEFAULT_RAKE_BPS);
        $rakeCents = $tournament->status === Tournament::STATUS_SETTLED && (int) $tournament->platform_rake_cents > 0
            ? (int) $tournament->platform_rake_cents
            : intdiv($referencePoolCents * $rakeBps, 10000);

        $netPoolCents = $tournament->status === Tournament::STATUS_SETTLED && (int) $tournament->net_prize_pool_cents > 0
            ? (int) $tournament->net_prize_pool_cents
            : ($referencePoolCents - $rakeCents);

        $secondPrizeCents = intdiv($netPoolCents * 3000, 10000);
        $thirdPrizeCents = $tournament->format === '1v1' && $tournament->max_participants <= 2
            ? 0
            : intdiv($netPoolCents * 1000, 10000);
        $firstPrizeCents = $netPoolCents - $secondPrizeCents - $thirdPrizeCents;

        $financialBreakdown = [
            'entry_fee_cents' => (int) $tournament->entry_fee_cents,
            'escrow_pool_cents' => $grossPoolCents,
            'reference_pool_cents' => $referencePoolCents,
            'rake_bps' => $rakeBps,
            'platform_rake_cents' => $rakeCents,
            'net_prize_pool_cents' => $netPoolCents,
            'first_prize_cents' => $firstPrizeCents,
            'second_prize_cents' => $secondPrizeCents,
            'third_prize_cents' => $thirdPrizeCents,
        ];

        // Build deterministic Bracket (Semi-Finals, 3rd Place Match, Grand Final)
        $regs = $tournament->registrations->values();
        $slot = function (int $index, string $fallbackTag) use ($regs): array {
            /** @var TournamentRegistration|null $reg */
            $reg = $regs->get($index);
            if ($reg !== null && $reg->user !== null) {
                return [
                    'user_id' => $reg->user->id,
                    'gamertag' => $reg->user->gamertag,
                    'elo_rating' => $reg->user->elo_rating,
                    'loadout_class' => $reg->loadout_class,
                    'status' => $reg->status,
                    'prize_won_cents' => $reg->prize_won_cents,
                ];
            }

            return [
                'user_id' => null,
                'gamertag' => $fallbackTag,
                'elo_rating' => 1500,
                'loadout_class' => 'VANGUARD',
                'status' => 'SEED_OPEN',
                'prize_won_cents' => 0,
            ];
        };

        $seed1 = $slot(0, 'SEED #1 // EN ATTENTE');
        $seed2 = $slot(1, 'SEED #2 // EN ATTENTE');
        $seed3 = $slot(2, 'SEED #3 // EN ATTENTE');
        $seed4 = $slot(3, 'SEED #4 // EN ATTENTE');

        $isSettled = $tournament->status === Tournament::STATUS_SETTLED;

        $bracket = [
            'semifinal_1' => [
                'code' => 'SF-01',
                'label' => 'DEMI-FINALE ALPHA',
                'player_a' => $seed1,
                'player_b' => $seed4,
                'score_a' => $isSettled ? 13 : ($regs->count() >= 4 ? 11 : 0),
                'score_b' => $isSettled ? 8 : ($regs->count() >= 4 ? 9 : 0),
                'status' => $isSettled ? 'SETTLED' : ($regs->count() >= 2 ? 'LIVE' : 'UPCOMING'),
            ],
            'semifinal_2' => [
                'code' => 'SF-02',
                'label' => 'DEMI-FINALE OMEGA',
                'player_a' => $seed2,
                'player_b' => $seed3,
                'score_a' => $isSettled ? 13 : ($regs->count() >= 3 ? 10 : 0),
                'score_b' => $isSettled ? 10 : ($regs->count() >= 3 ? 7 : 0),
                'status' => $isSettled ? 'SETTLED' : ($regs->count() >= 3 ? 'LIVE' : 'UPCOMING'),
            ],
            'third_place_match' => [
                'code' => 'PF-3RD',
                'label' => 'PETITE FINALE // 3E PLACE (10%)',
                'prize_cents' => $thirdPrizeCents,
                'player_a' => $isSettled && $tournament->thirdPlace !== null
                    ? ['gamertag' => $tournament->thirdPlace->gamertag, 'elo_rating' => $tournament->thirdPlace->elo_rating]
                    : $seed3,
                'player_b' => $seed4,
                'score_a' => $isSettled ? 13 : 0,
                'score_b' => $isSettled ? 9 : 0,
                'winner_gamertag' => $tournament->thirdPlace?->gamertag,
                'status' => $isSettled ? 'SETTLED' : 'UPCOMING',
            ],
            'grand_final' => [
                'code' => 'GF-01',
                'label' => 'GRANDE FINALE SOUVERAINE (60% / 30%)',
                'first_prize_cents' => $firstPrizeCents,
                'second_prize_cents' => $secondPrizeCents,
                'player_a' => $isSettled && $tournament->firstPlace !== null
                    ? ['gamertag' => $tournament->firstPlace->gamertag, 'elo_rating' => $tournament->firstPlace->elo_rating]
                    : $seed1,
                'player_b' => $isSettled && $tournament->secondPlace !== null
                    ? ['gamertag' => $tournament->secondPlace->gamertag, 'elo_rating' => $tournament->secondPlace->elo_rating]
                    : $seed2,
                'score_a' => $isSettled ? 13 : 0,
                'score_b' => $isSettled ? 11 : 0,
                'winner_gamertag' => $tournament->firstPlace?->gamertag,
                'runner_up_gamertag' => $tournament->secondPlace?->gamertag,
                'status' => $isSettled ? 'SETTLED' : 'UPCOMING',
            ],
        ];

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'tournament' => $tournament,
                'financial_breakdown' => $financialBreakdown,
                'bracket' => $bracket,
            ]);
        }

        /** @var User|null $currentUser */
        $currentUser = $request->user();
        $wallet = $currentUser !== null ? $ledgerService->getOrCreateWallet($currentUser) : null;
        $myRegistration = $currentUser !== null
            ? $tournament->registrations->firstWhere('user_id', $currentUser->id)
            : null;

        $canManage = $currentUser !== null && (
            $currentUser->isAdmin()
            || ($currentUser->isOrganizer() && (int) $tournament->organizer_id === (int) $currentUser->id)
        );

        return view('tournaments.show', [
            'tournament' => $tournament,
            'financialBreakdown' => $financialBreakdown,
            'bracket' => $bracket,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
            'myRegistration' => $myRegistration,
            'canManage' => $canManage,
        ]);
    }

    public function store(StoreTournamentRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $organizer */
        $organizer = $request->user();
        $validated = $request->validated();

        $baseSlug = Str::slug((string) $validated['title']);
        $slug = $baseSlug.'-'.strtolower(Str::random(6));

        $tournament = Tournament::create([
            'organizer_id' => $organizer->id,
            'slug' => $slug,
            'title' => (string) $validated['title'],
            'game' => (string) $validated['game'],
            'format' => (string) $validated['format'],
            'status' => Tournament::STATUS_OPEN,
            'entry_fee_cents' => (int) $validated['entry_fee_cents'],
            'escrow_pool_cents' => 0,
            'sponsored_guarantee_cents' => 0,
            'rake_bps' => Tournament::DEFAULT_RAKE_BPS,
            'platform_rake_cents' => 0,
            'net_prize_pool_cents' => 0,
            'max_participants' => (int) $validated['max_participants'],
            'registered_count' => 0,
            'starts_at' => isset($validated['starts_at']) ? $validated['starts_at'] : now()->addHours(2),
        ]);

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Tournoi créé et protocole de séquestre armé.',
                'tournament' => $tournament->load(['organizer', 'registrations.user']),
            ], 201);
        }

        return redirect()->route('tournaments.show', ['tournament' => $tournament->slug])
            ->with('status', "Arène « {$tournament->title} » ouverte aux inscriptions.");
    }

    public function register(
        RegisterTournamentRequest $request,
        Tournament $tournament,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        /** @var User $player */
        $player = $request->user();
        $validated = $request->validated();

        $result = $ledgerService->registerForTournament(
            player: $player,
            tournament: $tournament,
            idempotencyKey: (string) $validated['idempotency_key'],
            loadoutClass: isset($validated['loadout_class']) ? (string) $validated['loadout_class'] : null,
        );

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $result['idempotent_replay']
                    ? 'Inscription déjà confirmée (réponse idempotente).'
                    : 'Buy-in verrouillé en séquestre et inscription confirmée.',
                'tournament' => $result['tournament'],
                'registration' => $result['registration'],
                'wallet' => $result['wallet'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', "Buy-in verrouillé en séquestre pour « {$tournament->title} ».");
    }

    public function settle(
        SettleTournamentRequest $request,
        Tournament $tournament,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $result = $ledgerService->settleTournament(
            actor: $actor,
            tournament: $tournament,
            firstPlaceUserId: (int) $validated['first_place_user_id'],
            secondPlaceUserId: (int) $validated['second_place_user_id'],
            thirdPlaceUserId: isset($validated['third_place_user_id']) && $validated['third_place_user_id'] !== null
                ? (int) $validated['third_place_user_id']
                : null,
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $result['idempotent_replay']
                    ? 'Tournoi déjà clôturé (réponse idempotente).'
                    : 'Tournoi clôturé : commission 8 % prélevée et cashprize 60/30/10 distribué.',
                'tournament' => $result['tournament'],
                'breakdown' => $result['breakdown'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', "Tournoi « {$tournament->title} » clôturé et cashprize distribué.");
    }

    public function cancel(
        CancelTournamentRequest $request,
        Tournament $tournament,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $result = $ledgerService->cancelTournament(
            actor: $actor,
            tournament: $tournament,
            idempotencyKey: (string) $validated['idempotency_key'],
            reason: isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Tournoi annulé et 100 % des fonds séquestrés remboursés aux joueurs.',
                'tournament' => $result['tournament'],
                'refunded_count' => $result['refunded_count'],
                'total_refunded_cents' => $result['total_refunded_cents'],
            ], 200);
        }

        return back()->with('status', "Tournoi « {$tournament->title} » annulé et séquestre intégralement remboursé.");
    }
}
