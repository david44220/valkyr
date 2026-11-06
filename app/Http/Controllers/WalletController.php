<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DepositWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
use App\Models\User;
use App\Models\UserPaymentMethod;
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
            description: 'Dépôt de fonds sur portefeuille VALKYR // ARENA',
            paymentRailId: isset($validated['payment_rail_id']) && $validated['payment_rail_id'] !== null
                ? (int) $validated['payment_rail_id']
                : null,
            referenceCode: isset($validated['reference_code']) ? (string) $validated['reference_code'] : null,
        );

        $isPending = (bool) ($result['pending_approval'] ?? false);
        $statusStr = $isPending ? 'PENDING_ADMIN' : 'SETTLED';
        $statusCode = $result['idempotent_replay'] ? 200 : ($isPending ? 202 : 201);

        $message = $result['idempotent_replay']
            ? 'Dépôt déjà traité (réponse idempotente).'
            : ($isPending
                ? 'Demande de dépôt enregistrée en file d\'attente Admin (PENDING_ADMIN).'
                : 'Dépôt crédité avec succès sur votre portefeuille.');

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $statusStr,
                'pending_approval' => $isPending,
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'funding_request' => $result['funding_request'] ?? null,
                'fee_cents' => $result['fee_cents'] ?? 0,
                'net_amount_cents' => $result['net_amount_cents'] ?? (int) $validated['amount_cents'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], $statusCode);
        }

        return back()->with('status', $message);
    }

    public function withdraw(WithdrawWalletRequest $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $paymentRailId = isset($validated['payment_rail_id']) && $validated['payment_rail_id'] !== null
            ? (int) $validated['payment_rail_id']
            : null;
        $userPaymentMethodId = isset($validated['user_payment_method_id']) && $validated['user_payment_method_id'] !== null
            ? (int) $validated['user_payment_method_id']
            : null;

        $iban = isset($validated['iban']) && trim((string) $validated['iban']) !== ''
            ? trim((string) $validated['iban'])
            : 'FR76 3000 6000 0112 3456 7890 189';

        if ($userPaymentMethodId !== null) {
            /** @var UserPaymentMethod|null $savedMethod */
            $savedMethod = UserPaymentMethod::where('id', $userPaymentMethodId)
                ->where('user_id', $user->id)
                ->first();

            if ($savedMethod === null) {
                abort(403, 'Ce moyen de paiement enregistré ne vous appartient pas.');
            }

            $iban = $savedMethod->account_identifier;
            if ($paymentRailId === null) {
                $paymentRailId = (int) $savedMethod->payment_rail_id;
            }
        }

        $result = $ledgerService->withdraw(
            user: $user,
            amountCents: (int) $validated['amount_cents'],
            idempotencyKey: (string) $validated['idempotency_key'],
            iban: $iban,
            paymentRailId: $paymentRailId,
            userPaymentMethodId: $userPaymentMethodId,
        );

        $isPending = (bool) ($result['pending_approval'] ?? false);
        $statusStr = $isPending ? 'PENDING_ADMIN' : 'SETTLED';
        $message = $result['idempotent_replay']
            ? 'Retrait déjà exécuté (réponse idempotente).'
            : ($isPending
                ? 'Demande de retrait soumise pour approbation Admin (PENDING_ADMIN).'
                : 'Retrait exécuté avec succès depuis votre Wallet.');

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $statusStr,
                'pending_approval' => $isPending,
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'funding_request' => $result['funding_request'] ?? null,
                'fee_cents' => $result['fee_cents'] ?? 0,
                'net_amount_cents' => $result['net_amount_cents'] ?? (int) $validated['amount_cents'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    public function subscribePrime(Request $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->subscribePrime(
            user: $user,
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $message = $result['idempotent_replay']
            ? 'Abonnement VALKYR PRIME déjà activé (réponse idempotente).'
            : 'Abonnement ★ VALKYR PRIME activé (19,00 € débités de votre Wallet — 0 % de frais sur les rails).';

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'user' => $result['user'],
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    public function storePaymentMethod(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'payment_rail_id' => ['required', 'integer', 'exists:payment_rails,id'],
            'label' => ['required', 'string', 'min:2', 'max:100'],
            'account_identifier' => ['required', 'string', 'min:4', 'max:160'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = (bool) ($validated['is_default'] ?? false);
        if ($isDefault) {
            UserPaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $method = UserPaymentMethod::create([
            'user_id' => $user->id,
            'payment_rail_id' => (int) $validated['payment_rail_id'],
            'label' => trim((string) $validated['label']),
            'account_identifier' => trim((string) $validated['account_identifier']),
            'is_default' => $isDefault,
            'is_verified' => true,
        ]);

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Moyen de paiement enregistré avec succès.',
                'payment_method' => $method->load('paymentRail'),
            ], 201);
        }

        return back()->with('status', "Moyen de paiement « {$method->label} » enregistré.");
    }

    public function destroyPaymentMethod(Request $request, UserPaymentMethod $userPaymentMethod): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $userPaymentMethod->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'Vous ne pouvez supprimer que vos propres moyens de paiement.');
        }

        $label = $userPaymentMethod->label;
        $userPaymentMethod->delete();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Moyen de paiement supprimé.',
            ], 200);
        }

        return back()->with('status', "Moyen de paiement « {$label} » supprimé.");
    }

    public function redeemVoucher(Request $request, EscrowLedgerService $ledgerService): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40'],
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ]);

        $result = $ledgerService->redeemVoucher(
            user: $user,
            code: (string) $validated['code'],
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        $formatted = Wallet::formatCents((int) $result['redemption']->credited_cents);
        $message = $result['idempotent_replay']
            ? "Code Voucher « {$result['voucher']->code} » déjà crédité (réponse idempotente)."
            : "Code Voucher « {$result['voucher']->code} » activé : +{$formatted} crédités sur votre Wallet.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'voucher' => $result['voucher'],
                'redemption' => $result['redemption'],
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }
}
