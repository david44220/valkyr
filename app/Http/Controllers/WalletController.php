<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DepositWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
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

    public function withdraw(WithdrawWalletRequest $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $iban = isset($validated['iban']) && trim((string) $validated['iban']) !== ''
            ? trim((string) $validated['iban'])
            : 'FR76 3000 6000 0112 3456 7890 189';

        $result = $ledgerService->withdraw(
            user: $user,
            amountCents: (int) $validated['amount_cents'],
            idempotencyKey: (string) $validated['idempotency_key'],
            iban: $iban,
        );

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $result['idempotent_replay']
                    ? 'Retrait SEPA déjà exécuté (réponse idempotente).'
                    : 'Retrait bancaire SEPA Instant exécuté avec succès.',
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', 'Retrait SEPA de '.Wallet::formatCents((int) $validated['amount_cents']).' exécuté avec succès.');
    }
}
