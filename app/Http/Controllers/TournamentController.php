<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelTournamentRequest;
use App\Http\Requests\RegisterTournamentRequest;
use App\Http\Requests\SettleTournamentRequest;
use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use App\Models\User;
use App\Services\EscrowLedgerService;
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

    public function show(Tournament $tournament): JsonResponse
    {
        $tournament->load([
            'organizer',
            'registrations.user',
            'firstPlace',
            'secondPlace',
            'thirdPlace',
            'ledgerEntries.actor',
        ]);

        return response()->json([
            'tournament' => $tournament,
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

        return back()->with('status', "Arène « {$tournament->title} » ouverte aux inscriptions.");
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
