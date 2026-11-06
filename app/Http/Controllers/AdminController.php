<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\LedgerEntry;
use App\Models\PaymentRail;
use App\Models\Tournament;
use App\Models\TournamentDispute;
use App\Models\User;
use App\Models\VoucherCode;
use App\Models\WagerDuel;
use App\Models\Wallet;
use App\Models\WalletFundingRequest;
use App\Services\EscrowLedgerService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminController extends Controller
{
    private function assertAdmin(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || ! $user->isAdmin()) {
            abort(403, 'Accès refusé : le Commandement Souverain (/admin) est strictement réservé au rôle ADMIN.');
        }

        return $user;
    }

    /**
     * Page 5 — Sovereign Admin Back-Office & Treasury Command Center (GET /admin & GET /api/admin/state)
     */
    public function index(Request $request, EscrowLedgerService $ledgerService): View|JsonResponse
    {
        $admin = $this->assertAdmin($request);

        $systemWallets = $ledgerService->ensureSystemWallets();
        $ledgerService->ensureDefaultPaymentRails();
        $ledgerService->ensureDefaultArmoryItems();

        $treasuryWallet = $systemWallets['treasury']->fresh();
        $escrowWallet = $systemWallets['escrow']->fresh();

        $sumsByType = LedgerEntry::query()
            ->selectRaw('entry_type, COALESCE(SUM(credit_cents), 0) as total_credits, COALESCE(SUM(debit_cents), 0) as total_debits')
            ->groupBy('entry_type')
            ->get()
            ->keyBy('entry_type');

        $totalDepositsGrossCents = (int) ($sumsByType->get(LedgerEntry::TYPE_DEPOSIT)?->total_credits ?? 0);
        $totalWithdrawalsNetCents = (int) ($sumsByType->get(LedgerEntry::TYPE_WITHDRAWAL)?->total_debits ?? 0);
        $netTournamentRakeCents = (int) ($sumsByType->get(LedgerEntry::TYPE_PLATFORM_RAKE_CREDIT)?->total_credits ?? 0);
        $paymentRailFeesCents = (int) ($sumsByType->get(LedgerEntry::TYPE_PLATFORM_PAYMENT_FEE_CREDIT)?->total_credits ?? 0);
        $primeSubscriptionsRevenueCents = (int) ($sumsByType->get(LedgerEntry::TYPE_PRIME_SUBSCRIPTION_CREDIT)?->total_credits ?? 0);
        $arenaBoostsRevenueCents = (int) ($sumsByType->get(LedgerEntry::TYPE_ARENA_BOOST_CREDIT)?->total_credits ?? 0);
        $organizerRevsharePaidCents = (int) ($sumsByType->get(LedgerEntry::TYPE_ORGANIZER_RAKE_SHARE_CREDIT)?->total_credits ?? 0);
        $sponsoredPoolInjectedCents = (int) ($sumsByType->get(LedgerEntry::TYPE_SPONSOR_POOL_CREDIT)?->total_credits ?? 0);
        $armoryRevenueCents = (int) ($sumsByType->get(LedgerEntry::TYPE_ARMORY_REVENUE_CREDIT)?->total_credits ?? 0);
        $clanLicensesRevenueCents = (int) ($sumsByType->get(LedgerEntry::TYPE_CLAN_LICENSE_CREDIT)?->total_credits ?? 0);
        $disputeForfeitsRevenueCents = (int) ($sumsByType->get(LedgerEntry::TYPE_DISPUTE_FORFEIT_CREDIT)?->total_credits ?? 0);
        $duelRakeCents = (int) WagerDuel::where('status', WagerDuel::STATUS_SETTLED)->sum('platform_rake_cents');

        $revenueMetrics = [
            'treasury_balance_cents' => (int) $treasuryWallet->balance_cents,
            'escrow_balance_cents' => (int) $escrowWallet->balance_cents,
            'total_deposits_gross_cents' => $totalDepositsGrossCents,
            'total_withdrawals_net_cents' => $totalWithdrawalsNetCents,
            'net_tournament_rake_cents' => $netTournamentRakeCents,
            'payment_rail_fees_cents' => $paymentRailFeesCents,
            'prime_subscriptions_revenue_cents' => $primeSubscriptionsRevenueCents,
            'arena_boosts_revenue_cents' => $arenaBoostsRevenueCents,
            'organizer_revshare_paid_cents' => $organizerRevsharePaidCents,
            'sponsored_pool_injected_cents' => $sponsoredPoolInjectedCents,
            'armory_revenue_cents' => $armoryRevenueCents,
            'clan_licenses_revenue_cents' => $clanLicensesRevenueCents,
            'dispute_forfeits_revenue_cents' => $disputeForfeitsRevenueCents,
            'duel_rake_cents' => $duelRakeCents,
        ];

        $allWallets = Wallet::all();
        $netByWalletId = LedgerEntry::query()
            ->selectRaw('wallet_id, COALESCE(SUM(credit_cents), 0) - COALESCE(SUM(debit_cents), 0) as net_cents')
            ->groupBy('wallet_id')
            ->pluck('net_cents', 'wallet_id');

        $allWalletsValid = true;
        foreach ($allWallets as $w) {
            $expectedBalance = (int) ($netByWalletId->get($w->id) ?? 0);
            if ((int) $w->balance_cents !== $expectedBalance) {
                $allWalletsValid = false;
                break;
            }
        }
        $sumAllWalletsCents = (int) $allWallets->sum('balance_cents');
        $globalConservationHolds = ($totalDepositsGrossCents - $totalWithdrawalsNetCents) === $sumAllWalletsCents;

        $ledgerHealth = [
            'is_healthy' => $allWalletsValid && $globalConservationHolds,
            'wallets_checked_count' => $allWallets->count(),
            'all_wallets_invariant_ok' => $allWalletsValid,
            'global_conservation_holds' => $globalConservationHolds,
            'total_deposits_cents' => $totalDepositsGrossCents,
            'total_withdrawals_cents' => $totalWithdrawalsNetCents,
            'sum_all_wallets_cents' => $sumAllWalletsCents,
        ];

        $paymentRails = PaymentRail::orderBy('id')->get();

        $fundingRequests = WalletFundingRequest::with(['user', 'paymentRail', 'processedByAdmin'])
            ->orderByRaw("CASE status WHEN 'PENDING_ADMIN' THEN 0 WHEN 'SETTLED' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $pendingFundingCount = $fundingRequests
            ->where('status', WalletFundingRequest::STATUS_PENDING_ADMIN)
            ->count();

        $operators = User::with('wallet')
            ->whereNotIn('email', [
                EscrowLedgerService::ESCROW_VAULT_EMAIL,
                EscrowLedgerService::PLATFORM_TREASURY_EMAIL,
            ])
            ->orderBy('id')
            ->get();

        $tournaments = Tournament::with(['organizer', 'registrations.user'])
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->get();

        $masterLedgerEntries = LedgerEntry::with(['wallet.user', 'tournament', 'actor'])
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        $voucherCodes = VoucherCode::orderByDesc('id')->get();

        $disputes = TournamentDispute::with(['tournament', 'challenger', 'resolvedByAdmin'])
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get();

        $openDisputesCount = $disputes->where('status', TournamentDispute::STATUS_OPEN)->count();

        $wagerDuels = WagerDuel::with(['challenger', 'opponent', 'winner'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $clans = Clan::with('owner')->orderByDesc('id')->get();

        $demoUsers = User::with('wallet')
            ->whereIn('email', AuthController::ALLOWED_DEMO_EMAILS)
            ->get()
            ->keyBy('email');

        $requestedPanel = strtolower(trim((string) $request->query('panel', 'treasury')));
        if (! in_array($requestedPanel, ['treasury', 'rails', 'users', 'tournaments', 'vouchers', 'disputes', 'ledger'], true)) {
            $requestedPanel = 'treasury';
        }

        $payload = [
            'currentUser' => $admin,
            'wallet' => $ledgerService->getOrCreateWallet($admin),
            'activePanel' => $requestedPanel,
            'revenueMetrics' => $revenueMetrics,
            'ledgerHealth' => $ledgerHealth,
            'paymentRails' => $paymentRails,
            'fundingRequests' => $fundingRequests,
            'pendingFundingCount' => $pendingFundingCount,
            'operators' => $operators,
            'tournaments' => $tournaments,
            'masterLedgerEntries' => $masterLedgerEntries,
            'voucherCodes' => $voucherCodes,
            'disputes' => $disputes,
            'openDisputesCount' => $openDisputesCount,
            'wagerDuels' => $wagerDuels,
            'clans' => $clans,
            'demoUsers' => $demoUsers,
        ];

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.index', $payload);
    }

    public function storePaymentRail(Request $request): JsonResponse|RedirectResponse
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:64', 'unique:payment_rails,code'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'provider' => ['required', 'string', 'min:2', 'max:80'],
            'method_type' => ['required', 'string', Rule::in([
                PaymentRail::TYPE_BANK_TRANSFER,
                PaymentRail::TYPE_CARD,
                PaymentRail::TYPE_CRYPTO,
                PaymentRail::TYPE_MANUAL_VOUCHER,
            ])],
            'supports_deposit' => ['nullable', 'boolean'],
            'supports_withdrawal' => ['nullable', 'boolean'],
            'requires_admin_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'deposit_fee_bps' => ['required', 'integer', 'min:0', 'max:2000'],
            'deposit_fixed_fee_cents' => ['required', 'integer', 'min:0', 'max:50000'],
            'withdrawal_fee_bps' => ['required', 'integer', 'min:0', 'max:2000'],
            'withdrawal_fixed_fee_cents' => ['required', 'integer', 'min:0', 'max:50000'],
            'min_amount_cents' => ['required', 'integer', 'min:100', 'max:10000000'],
            'max_amount_cents' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'account_instructions' => ['nullable', 'string', 'max:500'],
            'settlement_delay_label' => ['nullable', 'string', 'max:60'],
        ]);

        $rail = PaymentRail::create([
            'code' => strtoupper(trim((string) $validated['code'])),
            'name' => trim((string) $validated['name']),
            'provider' => trim((string) $validated['provider']),
            'method_type' => (string) $validated['method_type'],
            'supports_deposit' => (bool) ($validated['supports_deposit'] ?? true),
            'supports_withdrawal' => (bool) ($validated['supports_withdrawal'] ?? true),
            'requires_admin_approval' => (bool) ($validated['requires_admin_approval'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'deposit_fee_bps' => (int) $validated['deposit_fee_bps'],
            'deposit_fixed_fee_cents' => (int) $validated['deposit_fixed_fee_cents'],
            'withdrawal_fee_bps' => (int) $validated['withdrawal_fee_bps'],
            'withdrawal_fixed_fee_cents' => (int) $validated['withdrawal_fixed_fee_cents'],
            'min_amount_cents' => (int) $validated['min_amount_cents'],
            'max_amount_cents' => (int) $validated['max_amount_cents'],
            'account_instructions' => $validated['account_instructions'] ?? null,
            'settlement_delay_label' => $validated['settlement_delay_label'] ?? 'INSTANTANÉ',
        ]);

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => "Rail de paiement « {$rail->name} » créé avec succès.",
                'payment_rail' => $rail,
            ], 201);
        }

        return back()->with('status', "Rail de paiement « {$rail->name} » créé.");
    }

    public function updatePaymentRail(Request $request, PaymentRail $paymentRail): JsonResponse|RedirectResponse
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'min:3', 'max:120'],
            'provider' => ['nullable', 'string', 'min:2', 'max:80'],
            'supports_deposit' => ['nullable', 'boolean'],
            'supports_withdrawal' => ['nullable', 'boolean'],
            'requires_admin_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'deposit_fee_bps' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'deposit_fixed_fee_cents' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'withdrawal_fee_bps' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'withdrawal_fixed_fee_cents' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'min_amount_cents' => ['nullable', 'integer', 'min:100', 'max:10000000'],
            'max_amount_cents' => ['nullable', 'integer', 'min:1000', 'max:100000000'],
            'account_instructions' => ['nullable', 'string', 'max:500'],
            'settlement_delay_label' => ['nullable', 'string', 'max:60'],
        ]);

        foreach ([
            'name',
            'provider',
            'supports_deposit',
            'supports_withdrawal',
            'requires_admin_approval',
            'is_active',
            'deposit_fee_bps',
            'deposit_fixed_fee_cents',
            'withdrawal_fee_bps',
            'withdrawal_fixed_fee_cents',
            'min_amount_cents',
            'max_amount_cents',
            'account_instructions',
            'settlement_delay_label',
        ] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $paymentRail->{$field} = $validated[$field];
            }
        }

        $paymentRail->save();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => "Configuration du rail « {$paymentRail->name} » mise à jour.",
                'payment_rail' => $paymentRail,
            ], 200);
        }

        return back()->with('status', "Rail « {$paymentRail->name} » mis à jour.");
    }

    public function approveFundingRequest(
        Request $request,
        WalletFundingRequest $fundingRequest,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        $admin = $this->assertAdmin($request);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $ledgerService->approveFundingRequest(
            admin: $admin,
            fundingRequest: $fundingRequest,
            adminNotes: isset($validated['admin_notes']) ? (string) $validated['admin_notes'] : null,
        );

        $message = $result['idempotent_replay']
            ? 'Demande déjà approuvée et réglée (réponse idempotente).'
            : "Demande #{$fundingRequest->id} ({$fundingRequest->direction}) approuvée et réglée sur le Grand Livre.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'funding_request' => $result['funding_request']->load(['user', 'paymentRail', 'processedByAdmin']),
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    public function rejectFundingRequest(
        Request $request,
        WalletFundingRequest $fundingRequest,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        $admin = $this->assertAdmin($request);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $ledgerService->rejectFundingRequest(
            admin: $admin,
            fundingRequest: $fundingRequest,
            adminNotes: isset($validated['admin_notes']) ? (string) $validated['admin_notes'] : null,
        );

        $message = "Demande #{$fundingRequest->id} rejetée par l'Administration.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'funding_request' => $updated->load(['user', 'paymentRail', 'processedByAdmin']),
            ], 200);
        }

        return back()->with('status', $message);
    }

    public function updateUserStatus(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->assertAdmin($request);

        if (in_array($user->email, [
            EscrowLedgerService::ESCROW_VAULT_EMAIL,
            EscrowLedgerService::PLATFORM_TREASURY_EMAIL,
        ], true)) {
            abort(422, 'Les comptes système internes (Vault & Treasury) ne peuvent pas être modifiés.');
        }

        $validated = $request->validate([
            'role' => ['nullable', 'string', Rule::in([User::ROLE_PLAYER, User::ROLE_ORGANIZER, User::ROLE_ADMIN])],
            'is_prime' => ['nullable', 'boolean'],
            'is_frozen' => ['nullable', 'boolean'],
            'kyc_status' => ['nullable', 'string', Rule::in([
                User::KYC_STANDARD,
                User::KYC_VERIFIED_PRO,
                User::KYC_INSTITUTIONAL,
            ])],
        ]);

        if (array_key_exists('role', $validated) && $validated['role'] !== null) {
            $user->role = (string) $validated['role'];
        }

        if (array_key_exists('is_prime', $validated) && $validated['is_prime'] !== null) {
            $user->is_prime = (bool) $validated['is_prime'];
            $user->prime_expires_at = $user->is_prime ? now()->addDays(30) : null;
        }

        if (array_key_exists('is_frozen', $validated) && $validated['is_frozen'] !== null) {
            $user->is_frozen = (bool) $validated['is_frozen'];
        }

        if (array_key_exists('kyc_status', $validated) && $validated['kyc_status'] !== null) {
            $user->kyc_status = (string) $validated['kyc_status'];
        }

        $user->save();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => "Statut de l'opérateur {$user->gamertag} mis à jour.",
                'user' => $user->fresh('wallet'),
            ], 200);
        }

        return back()->with('status', "Statut de l'opérateur {$user->gamertag} mis à jour.");
    }

    public function creditUserWallet(
        Request $request,
        User $user,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'amount_cents' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_float($value) || is_bool($value) || ! preg_match('/^-?\d+$/', (string) $value)) {
                        $fail('Le montant de la dotation doit être exprimé en centimes entiers.');
                    }
                },
                'integer',
                'min:100',
                'max:10000000',
            ],
            'reason' => ['nullable', 'string', 'max:180'],
            'idempotency_key' => ['nullable', 'string', 'min:4', 'max:140'],
        ]);

        $amountCents = (int) $validated['amount_cents'];
        $reason = isset($validated['reason']) && trim((string) $validated['reason']) !== ''
            ? trim((string) $validated['reason'])
            : "Dotation commerciale Admin // {$user->gamertag}";
        $idempotencyKey = isset($validated['idempotency_key']) && trim((string) $validated['idempotency_key']) !== ''
            ? trim((string) $validated['idempotency_key'])
            : ('admin:credit:'.$user->id.':'.strtolower(Str::random(10)));

        $result = $ledgerService->deposit(
            user: $user,
            amountCents: $amountCents,
            idempotencyKey: $idempotencyKey,
            description: $reason,
        );

        $formatted = Wallet::formatCents($amountCents);
        $message = "Dotation de {$formatted} créditée sur le Wallet de {$user->gamertag}.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'wallet' => $result['wallet'],
                'ledger_entry' => $result['ledger_entry'],
            ], 200);
        }

        return back()->with('status', $message);
    }

    public function configureTournament(Request $request, Tournament $tournament): JsonResponse|RedirectResponse
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'rake_bps' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'organizer_share_bps' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('rake_bps', $validated) && $validated['rake_bps'] !== null) {
            $tournament->rake_bps = (int) $validated['rake_bps'];
        }

        if (array_key_exists('organizer_share_bps', $validated) && $validated['organizer_share_bps'] !== null) {
            $tournament->organizer_share_bps = min((int) $validated['organizer_share_bps'], (int) $tournament->rake_bps);
        }

        if (array_key_exists('is_featured', $validated) && $validated['is_featured'] !== null) {
            $tournament->is_featured = (bool) $validated['is_featured'];
        }

        $tournament->save();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => "Paramètres de l'arène « {$tournament->title} » mis à jour.",
                'tournament' => $tournament->fresh(['organizer', 'registrations.user']),
            ], 200);
        }

        return back()->with('status', "Paramètres de l'arène « {$tournament->title} » mis à jour.");
    }

    /**
     * Brick 5 — Create a Prepaid Voucher Code in /admin (POST /admin/vouchers & POST /api/admin/vouchers)
     */
    public function storeVoucher(Request $request): JsonResponse|RedirectResponse
    {
        $admin = $this->assertAdmin($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'unique:voucher_codes,code'],
            'credit_amount_cents' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_float($value) || is_bool($value) || ! preg_match('/^-?\d+$/', (string) $value)) {
                        $fail('Le montant du code Voucher doit être exprimé en centimes entiers.');
                    }
                },
                'integer',
                'min:100',
                'max:5000000',
            ],
            'max_uses' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $voucher = VoucherCode::create([
            'code' => strtoupper(trim((string) $validated['code'])),
            'credit_amount_cents' => (int) $validated['credit_amount_cents'],
            'max_uses' => (int) $validated['max_uses'],
            'used_count' => 0,
            'is_active' => true,
            'created_by_admin_id' => $admin->id,
        ]);

        $formatted = Wallet::formatCents((int) $voucher->credit_amount_cents);
        $message = "Code Voucher « {$voucher->code} » ({$formatted} × {$voucher->max_uses}) généré.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'voucher' => $voucher,
            ], 201);
        }

        return back()->with('status', $message);
    }

    /**
     * Brick 6 — Resolve a Tournament Dispute in /admin (POST /admin/disputes/{dispute}/resolve & /api/admin/disputes/{dispute}/resolve)
     */
    public function resolveDispute(
        Request $request,
        TournamentDispute $dispute,
        EscrowLedgerService $ledgerService
    ): JsonResponse|RedirectResponse {
        $admin = $this->assertAdmin($request);

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['UPHELD', 'REJECTED'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $ledgerService->resolveTournamentDispute(
            admin: $admin,
            dispute: $dispute,
            decision: (string) $validated['decision'],
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        $decisionLabel = $validated['decision'] === 'UPHELD'
            ? 'Litige fondé : caution de 5,00 € remboursée au joueur.'
            : 'Litige rejeté : caution d\'arbitrage de 5,00 € acquise par SYS_TREASURY.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $decisionLabel,
                'dispute' => $result['dispute'],
                'idempotent_replay' => $result['idempotent_replay'],
            ], 200);
        }

        return back()->with('status', $decisionLabel);
    }

    /**
     * Brick 7 — Certified CSV Ledger Export (GET /admin/export/ledger.csv & GET /api/admin/export/ledger.csv)
     */
    public function exportLedgerCsv(Request $request): Response
    {
        $this->assertAdmin($request);

        $entries = LedgerEntry::with(['wallet.user', 'tournament', 'actor'])
            ->orderBy('id')
            ->get();

        $lines = [
            'id,created_at,wallet_id,operator_gamertag,entry_type,debit_cents,credit_cents,balance_before_cents,balance_after_cents,tournament_slug,idempotency_key,description',
        ];

        foreach ($entries as $entry) {
            $gamertag = $this->sanitizeCsvCell((string) ($entry->wallet?->user?->gamertag ?? 'SYSTEM'));
            $slug = $this->sanitizeCsvCell((string) ($entry->tournament?->slug ?? ''));
            $idem = $this->sanitizeCsvCell((string) $entry->idempotency_key);
            $desc = $this->sanitizeCsvCell((string) $entry->description);

            $lines[] = sprintf(
                '%d,"%s",%d,"%s","%s",%d,%d,%d,%d,"%s","%s","%s"',
                (int) $entry->id,
                (string) $entry->created_at,
                (int) $entry->wallet_id,
                $gamertag,
                (string) $entry->entry_type,
                (int) $entry->debit_cents,
                (int) $entry->credit_cents,
                (int) $entry->balance_before_cents,
                (int) $entry->balance_after_cents,
                $slug,
                $idem,
                $desc
            );
        }

        $csv = implode("\r\n", $lines)."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="valkyr-grand-livre-audit.csv"',
        ]);
    }

    private function sanitizeCsvCell(string $value): string
    {
        $clean = str_replace(["\r", "\n"], ' ', $value);
        $trimmed = ltrim($clean);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t"], true)) {
            $clean = "'".$clean;
        }

        return str_replace('"', '""', $clean);
    }
}
