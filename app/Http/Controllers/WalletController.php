<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DepositWalletRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Services\EscrowLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class WalletController extends Controller
{
    public function show(Request $request, Wallet $wallet): JsonResponse
    {
        Gate::authorize('view', $wallet);

        $wallet->load([
            'user',
            'ledgerEntries.tournament',
            'ledgerEntries.actor',
        ]);

        return response()->json([
            'wallet' => $wallet,
            'ledger_entries' => $wallet->ledgerEntries,
        ]);
    }

    public function deposit(DepositWalletRequest $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $result = $ledgerService->deposit(
            user: $user,
            amountCents: (int) $validated['amount_cents'],
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $statusCode = $result['idempotent_replay'] ? 200 : 201;

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $result['idempotent_replay']
                    ? 'Dépôt déjà traité (réponse idempotente).'
                    : 'Dépôt crédité avec succès sur votre portefeuille.',
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], $statusCode);
        }

        return back()->with('status', 'Dépôt de '.Wallet::formatCents((int) $validated['amount_cents']).' crédité avec succès.');
    }
}
