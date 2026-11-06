<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InvalidFinancialOperationException;
use App\Exceptions\TournamentStateConflictException;
use App\Models\ArmoryItem;
use App\Models\Clan;
use App\Models\LedgerEntry;
use App\Models\PaymentRail;
use App\Models\Tournament;
use App\Models\TournamentDispute;
use App\Models\TournamentPrediction;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Models\UserArmoryPurchase;
use App\Models\VoucherCode;
use App\Models\VoucherRedemption;
use App\Models\WagerDuel;
use App\Models\Wallet;
use App\Models\WalletFundingRequest;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class EscrowLedgerService
{
    public const string ESCROW_VAULT_EMAIL = 'escrow.vault@valkyr.arena';

    public const string PLATFORM_TREASURY_EMAIL = 'treasury@valkyr.arena';

    public const int PRIME_SUBSCRIPTION_PRICE_CENTS = 1900; // 19,00 €

    public const int ARENA_BOOST_PRICE_CENTS = 2500; // 25,00 €

    public const int CLAN_LICENSE_PRICE_CENTS = 2900; // 29,00 €

    public const int DISPUTE_BOND_CENTS = 500; // 5,00 €

    /**
     * Ensure system users and their corresponding wallets exist.
     *
     * @return array{escrow: Wallet, treasury: Wallet}
     */
    public function ensureSystemWallets(): array
    {
        $escrowUser = User::where('email', self::ESCROW_VAULT_EMAIL)->first()
            ?? User::create([
                'email' => self::ESCROW_VAULT_EMAIL,
                'name' => 'VALKYR Escrow Vault',
                'gamertag' => 'SYS_ESCROW_VAULT',
                'password' => Hash::make('SystemVault_Unreachable_Key_99!'),
                'role' => User::ROLE_ADMIN,
                'elo_rating' => 3000,
                'preferred_class' => 'VANGUARD',
            ]);

        $treasuryUser = User::where('email', self::PLATFORM_TREASURY_EMAIL)->first()
            ?? User::create([
                'email' => self::PLATFORM_TREASURY_EMAIL,
                'name' => 'VALKYR Platform Treasury',
                'gamertag' => 'SYS_TREASURY',
                'password' => Hash::make('SystemTreasury_Unreachable_Key_99!'),
                'role' => User::ROLE_ADMIN,
                'elo_rating' => 3000,
                'preferred_class' => 'VALKYRIE',
            ]);

        return [
            'escrow' => $this->getOrCreateWallet($escrowUser),
            'treasury' => $this->getOrCreateWallet($treasuryUser),
        ];
    }

    /**
     * Ensure default configurable payment rails exist in the database.
     */
    public function ensureDefaultPaymentRails(): void
    {
        $defaults = [
            [
                'code' => 'SEPA_INSTANT',
                'name' => 'SEPA Instantané Express (Auto)',
                'provider' => 'Banque Souveraine SEPA',
                'method_type' => PaymentRail::TYPE_BANK_TRANSFER,
                'supports_deposit' => true,
                'supports_withdrawal' => true,
                'requires_admin_approval' => false,
                'is_active' => true,
                'deposit_fee_bps' => 0,
                'deposit_fixed_fee_cents' => 0,
                'withdrawal_fee_bps' => 0,
                'withdrawal_fixed_fee_cents' => 0,
                'min_amount_cents' => 100,
                'max_amount_cents' => 50000000,
                'account_instructions' => 'IBAN Souverain : FR76 3000 6000 0199 8877 6655 441 — BIC : VKRAPARIXXX — Règlement SEPA Instantané automatique sur Wallet.',
                'settlement_delay_label' => 'INSTANTANÉ (< 5S)',
            ],
            [
                'code' => 'SEPA_MANUAL',
                'name' => 'Virement Bancaire SEPA (Validation Admin)',
                'provider' => 'Trésorerie Manuelle VALKYR',
                'method_type' => PaymentRail::TYPE_BANK_TRANSFER,
                'supports_deposit' => true,
                'supports_withdrawal' => true,
                'requires_admin_approval' => true,
                'is_active' => true,
                'deposit_fee_bps' => 0,
                'deposit_fixed_fee_cents' => 0,
                'withdrawal_fee_bps' => 50,
                'withdrawal_fixed_fee_cents' => 100,
                'min_amount_cents' => 1000,
                'max_amount_cents' => 100000000,
                'account_instructions' => 'IBAN Trésorerie Manuelle : FR76 3000 6000 0100 1122 3344 999 — Indiquez obligatoirement votre Gamertag en référence. Réglé par un Officier Admin dans /admin.',
                'settlement_delay_label' => 'VALIDATION ADMIN (< 1H)',
            ],
            [
                'code' => 'STRIPE_CARD',
                'name' => 'Carte Bancaire Instantanée (Visa / Mastercard)',
                'provider' => 'Stripe Treasury 3DS',
                'method_type' => PaymentRail::TYPE_CARD,
                'supports_deposit' => true,
                'supports_withdrawal' => false,
                'requires_admin_approval' => false,
                'is_active' => true,
                'deposit_fee_bps' => 150,
                'deposit_fixed_fee_cents' => 25,
                'withdrawal_fee_bps' => 0,
                'withdrawal_fixed_fee_cents' => 0,
                'min_amount_cents' => 500,
                'max_amount_cents' => 10000000,
                'account_instructions' => ' Passerelle 3D-Secure v2 — Frais 1,50 % + 0,25 € (0 % pour les abonnés ★ VALKYR PRIME).',
                'settlement_delay_label' => 'INSTANTANÉ (< 2S)',
            ],
            [
                'code' => 'CRYPTO_USDT',
                'name' => 'Crypto USDT / USDC (ERC-20 & TRC-20)',
                'provider' => 'Tether / Circle On-Chain',
                'method_type' => PaymentRail::TYPE_CRYPTO,
                'supports_deposit' => true,
                'supports_withdrawal' => true,
                'requires_admin_approval' => false,
                'is_active' => true,
                'deposit_fee_bps' => 50,
                'deposit_fixed_fee_cents' => 0,
                'withdrawal_fee_bps' => 100,
                'withdrawal_fixed_fee_cents' => 100,
                'min_amount_cents' => 1000,
                'max_amount_cents' => 50000000,
                'account_instructions' => 'Vault On-Chain : 0x9F8c4E7A20B51D62E83c17A9004B2D819A11VKR — Indiquez le Hash TX (0x...) ou votre adresse de réception.',
                'settlement_delay_label' => 'ON-CHAIN (12 BLOCS)',
            ],
            [
                'code' => 'OTC_VOUCHER_MANUAL',
                'name' => 'Guichet OTC & Bon Trésorerie (Validation Admin)',
                'provider' => 'Commandement Souverain OTC',
                'method_type' => PaymentRail::TYPE_MANUAL_VOUCHER,
                'supports_deposit' => true,
                'supports_withdrawal' => true,
                'requires_admin_approval' => true,
                'is_active' => true,
                'deposit_fee_bps' => 100,
                'deposit_fixed_fee_cents' => 0,
                'withdrawal_fee_bps' => 100,
                'withdrawal_fixed_fee_cents' => 0,
                'min_amount_cents' => 2500,
                'max_amount_cents' => 100000000,
                'account_instructions' => 'Compensation institutionnelle ou Bon OTC validé manuellement par l\'Admin dans le Back-Office /admin.',
                'settlement_delay_label' => 'VALIDATION ADMIN',
            ],
        ];

        $existingCodes = PaymentRail::whereIn('code', array_column($defaults, 'code'))
            ->pluck('code')
            ->all();

        if (count($existingCodes) === count($defaults)) {
            return;
        }

        foreach ($defaults as $railData) {
            if (! in_array($railData['code'], $existingCodes, true)) {
                PaymentRail::firstOrCreate(
                    ['code' => $railData['code']],
                    $railData
                );
            }
        }
    }

    /**
     * Get or initialize a user's wallet with 0 integer cents.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance_cents' => 0,
                'locked_escrow_cents' => 0,
                'lifetime_won_cents' => 0,
                'currency' => 'EUR',
            ]
        );
    }

    /**
     * Ensure the user's wallet is not administratively frozen.
     */
    public function assertWalletNotFrozen(User $user): void
    {
        if ((bool) ($user->getAttributes()['is_frozen'] ?? false)) {
            throw new InvalidFinancialOperationException(
                "Opération refusée : le portefeuille de l'opérateur {$user->gamertag} est actuellement gelé par l'Administration Souveraine."
            );
        }
    }

    /**
     * Deposit integer cents into a user's wallet with optional PaymentRail fee calculation
     * and support for both instant settlement and manual Admin approval queue.
     *
     * @return array{
     *     wallet: Wallet,
     *     ledger_entry: LedgerEntry|null,
     *     funding_request?: WalletFundingRequest|null,
     *     pending_approval?: bool,
     *     fee_cents?: int,
     *     net_amount_cents?: int,
     *     idempotent_replay: bool
     * }
     */
    public function deposit(
        User $user,
        int $amountCents,
        string $idempotencyKey,
        string $description = 'Dépôt de fonds sur portefeuille VALKYR // ARENA',
        ?int $paymentRailId = null,
        ?string $referenceCode = null
    ): array {
        $this->assertWalletNotFrozen($user);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire.');
        }

        if ($amountCents <= 0 || $amountCents > 100_000_000) {
            throw new InvalidFinancialOperationException('Le montant du dépôt en centimes doit être un entier strictement positif (entre 1 et 100 000 000 centimes).');
        }

        $rail = null;
        $feeCents = 0;

        if ($paymentRailId !== null) {
            $rail = PaymentRail::find($paymentRailId);
            if ($rail === null || ! $rail->is_active || ! $rail->supports_deposit) {
                throw new InvalidFinancialOperationException('Cette méthode de dépôt est inactive ou non supportée.');
            }

            if ($amountCents < (int) $rail->min_amount_cents || $amountCents > (int) $rail->max_amount_cents) {
                throw new InvalidFinancialOperationException(
                    sprintf(
                        'Le montant pour le rail « %s » doit être compris entre %s et %s.',
                        $rail->name,
                        Wallet::formatCents((int) $rail->min_amount_cents),
                        Wallet::formatCents((int) $rail->max_amount_cents)
                    )
                );
            }

            $feeCents = $rail->calculateDepositFeeCents($amountCents, (bool) ($user->getAttributes()['is_prime'] ?? false));
            if ($feeCents >= $amountCents) {
                throw new InvalidFinancialOperationException('Le montant brut du dépôt doit être strictement supérieur aux frais de traitement.');
            }
        }

        $netAmountCents = $amountCents - $feeCents;
        $initialWallet = $this->getOrCreateWallet($user);
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];
        $cleanRef = $referenceCode !== null && trim($referenceCode) !== ''
            ? trim($referenceCode)
            : ('DEP-'.strtoupper(Str::random(8)));

        return DB::transaction(function () use (
            $user,
            $initialWallet,
            $treasuryWallet,
            $amountCents,
            $feeCents,
            $netAmountCents,
            $trimmedKey,
            $description,
            $rail,
            $cleanRef
        ): array {
            // 1. Check if a pending/settled funding request with this idempotency key already exists
            $existingRequest = WalletFundingRequest::where('idempotency_key', $trimmedKey)->first();
            if ($existingRequest !== null) {
                if (
                    (int) $existingRequest->wallet_id === (int) $initialWallet->id
                    && $existingRequest->direction === WalletFundingRequest::DIRECTION_DEPOSIT
                    && (int) $existingRequest->gross_amount_cents === $amountCents
                ) {
                    $entry = LedgerEntry::where('idempotency_key', $trimmedKey)->first();

                    return [
                        'wallet' => Wallet::whereKey($initialWallet->id)->firstOrFail(),
                        'ledger_entry' => $entry,
                        'funding_request' => $existingRequest,
                        'pending_approval' => $existingRequest->status === WalletFundingRequest::STATUS_PENDING_ADMIN,
                        'fee_cents' => (int) $existingRequest->fee_cents,
                        'net_amount_cents' => (int) $existingRequest->net_amount_cents,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Cette clé d\'idempotence a déjà été utilisée pour une demande différente.');
            }

            // 2. Check LedgerEntry idempotency
            $existingEntry = LedgerEntry::where('idempotency_key', $trimmedKey)->first();
            if ($existingEntry !== null) {
                if (
                    (int) $existingEntry->wallet_id === (int) $initialWallet->id
                    && $existingEntry->entry_type === LedgerEntry::TYPE_DEPOSIT
                    && (int) $existingEntry->credit_cents === $amountCents
                ) {
                    $freshWallet = Wallet::whereKey($initialWallet->id)->firstOrFail();

                    return [
                        'wallet' => $freshWallet,
                        'ledger_entry' => $existingEntry,
                        'funding_request' => null,
                        'pending_approval' => false,
                        'fee_cents' => $feeCents,
                        'net_amount_cents' => $netAmountCents,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Cette clé d\'idempotence a déjà été utilisée pour une transaction différente.');
            }

            // 3. If rail requires manual Admin approval, enqueue in wallet_funding_requests without mutating balance yet
            if ($rail !== null && $rail->requires_admin_approval) {
                $fundingRequest = WalletFundingRequest::create([
                    'user_id' => $user->id,
                    'wallet_id' => $initialWallet->id,
                    'payment_rail_id' => $rail->id,
                    'user_payment_method_id' => null,
                    'direction' => WalletFundingRequest::DIRECTION_DEPOSIT,
                    'gross_amount_cents' => $amountCents,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                    'reference_code' => $cleanRef,
                    'status' => WalletFundingRequest::STATUS_PENDING_ADMIN,
                    'idempotency_key' => $trimmedKey,
                ]);

                return [
                    'wallet' => Wallet::whereKey($initialWallet->id)->firstOrFail(),
                    'ledger_entry' => null,
                    'funding_request' => $fundingRequest,
                    'pending_approval' => true,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                    'idempotent_replay' => false,
                ];
            }

            // 4. Immediate settlement: lock wallets in deterministic ASC order
            $walletIds = array_values(array_unique([(int) $initialWallet->id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedUserWallet */
            $lockedUserWallet = $lockedWallets->get((int) $initialWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            $balanceBefore = (int) $lockedUserWallet->balance_cents;
            $balanceAfterGross = $balanceBefore + $amountCents;

            $lockedUserWallet->balance_cents = $balanceAfterGross;
            $lockedUserWallet->save();

            $entry = LedgerEntry::create([
                'wallet_id' => $lockedUserWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_DEPOSIT,
                'debit_cents' => 0,
                'credit_cents' => $amountCents,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents' => $balanceAfterGross,
                'idempotency_key' => $trimmedKey,
                'description' => $rail !== null ? "{$description} ({$rail->name})" : $description,
                'metadata' => [
                    'currency' => $lockedUserWallet->currency,
                    'gamertag' => $user->gamertag,
                    'rail_code' => $rail?->code ?? 'DIRECT_SEPA',
                    'reference_code' => $cleanRef,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                ],
            ]);

            // Deduct payment rail processing fee to Platform Treasury if applicable
            if ($feeCents > 0 && (int) $lockedUserWallet->id !== (int) $lockedTreasuryWallet->id) {
                $userFeeBefore = (int) $lockedUserWallet->balance_cents;
                $userFeeAfter = $userFeeBefore - $feeCents;
                $lockedUserWallet->balance_cents = $userFeeAfter;
                $lockedUserWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedUserWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $user->id,
                    'entry_type' => LedgerEntry::TYPE_DEPOSIT_FEE_DEBIT,
                    'debit_cents' => $feeCents,
                    'credit_cents' => 0,
                    'balance_before_cents' => $userFeeBefore,
                    'balance_after_cents' => $userFeeAfter,
                    'idempotency_key' => "{$trimmedKey}:fee_debit",
                    'description' => 'Frais de traitement dépôt — '.($rail?->name ?? 'Passerelle'),
                    'metadata' => [
                        'rail_code' => $rail?->code,
                        'gross_amount_cents' => $amountCents,
                    ],
                ]);

                $treasuryBefore = (int) $lockedTreasuryWallet->balance_cents;
                $treasuryAfter = $treasuryBefore + $feeCents;
                $lockedTreasuryWallet->balance_cents = $treasuryAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $user->id,
                    'entry_type' => LedgerEntry::TYPE_PLATFORM_PAYMENT_FEE_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $feeCents,
                    'balance_before_cents' => $treasuryBefore,
                    'balance_after_cents' => $treasuryAfter,
                    'idempotency_key' => "{$trimmedKey}:fee_credit",
                    'description' => "Commission passerelle dépôt ({$user->gamertag}) — ".($rail?->name ?? 'Passerelle'),
                    'metadata' => [
                        'rail_code' => $rail?->code,
                        'player_gamertag' => $user->gamertag,
                    ],
                ]);
            }

            $fundingRequest = null;
            if ($rail !== null) {
                $fundingRequest = WalletFundingRequest::create([
                    'user_id' => $user->id,
                    'wallet_id' => $lockedUserWallet->id,
                    'payment_rail_id' => $rail->id,
                    'user_payment_method_id' => null,
                    'direction' => WalletFundingRequest::DIRECTION_DEPOSIT,
                    'gross_amount_cents' => $amountCents,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                    'reference_code' => $cleanRef,
                    'status' => WalletFundingRequest::STATUS_SETTLED,
                    'idempotency_key' => $trimmedKey,
                    'processed_at' => now(),
                ]);
            }

            return [
                'wallet' => $lockedUserWallet,
                'ledger_entry' => $entry,
                'funding_request' => $fundingRequest,
                'pending_approval' => false,
                'fee_cents' => $feeCents,
                'net_amount_cents' => $netAmountCents,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Withdraw integer cents from a user's wallet with optional PaymentRail fee calculation,
     * pessimistic row locking, post-lock sufficiency check, and manual Admin approval support.
     *
     * @return array{
     *     wallet: Wallet,
     *     ledger_entry: LedgerEntry|null,
     *     funding_request?: WalletFundingRequest|null,
     *     pending_approval?: bool,
     *     fee_cents?: int,
     *     net_amount_cents?: int,
     *     idempotent_replay: bool
     * }
     */
    public function withdraw(
        User $user,
        int $amountCents,
        string $idempotencyKey,
        string $iban = 'FR76 3000 6000 0112 3456 7890 189',
        string $description = 'Retrait bancaire SEPA Instant depuis portefeuille VALKYR // ARENA',
        ?int $paymentRailId = null,
        ?int $userPaymentMethodId = null
    ): array {
        $this->assertWalletNotFrozen($user);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire pour le retrait.');
        }

        if ($amountCents < 100 || $amountCents > 50_000_000) {
            throw new InvalidFinancialOperationException('Le montant du retrait en centimes doit être un entier compris entre 100 (1,00 €) et 50 000 000 centimes.');
        }

        $rail = null;
        $feeCents = 0;

        if ($paymentRailId !== null) {
            $rail = PaymentRail::find($paymentRailId);
            if ($rail === null || ! $rail->is_active || ! $rail->supports_withdrawal) {
                throw new InvalidFinancialOperationException('Cette méthode de retrait est inactive ou non supportée.');
            }

            if ($amountCents < (int) $rail->min_amount_cents || $amountCents > (int) $rail->max_amount_cents) {
                throw new InvalidFinancialOperationException(
                    sprintf(
                        'Le montant pour le rail « %s » doit être compris entre %s et %s.',
                        $rail->name,
                        Wallet::formatCents((int) $rail->min_amount_cents),
                        Wallet::formatCents((int) $rail->max_amount_cents)
                    )
                );
            }

            $feeCents = $rail->calculateWithdrawalFeeCents($amountCents, (bool) ($user->getAttributes()['is_prime'] ?? false));
            if ($feeCents >= $amountCents) {
                throw new InvalidFinancialOperationException('Le montant brut du retrait doit être strictement supérieur aux frais de retrait.');
            }
        }

        $netAmountCents = $amountCents - $feeCents;
        $initialWallet = $this->getOrCreateWallet($user);
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use (
            $user,
            $initialWallet,
            $treasuryWallet,
            $amountCents,
            $feeCents,
            $netAmountCents,
            $trimmedKey,
            $iban,
            $description,
            $rail,
            $userPaymentMethodId
        ): array {
            $existingRequest = WalletFundingRequest::where('idempotency_key', $trimmedKey)->first();
            if ($existingRequest !== null) {
                if (
                    (int) $existingRequest->wallet_id === (int) $initialWallet->id
                    && $existingRequest->direction === WalletFundingRequest::DIRECTION_WITHDRAWAL
                    && (int) $existingRequest->gross_amount_cents === $amountCents
                ) {
                    $entry = LedgerEntry::where('idempotency_key', $trimmedKey)->first();

                    return [
                        'wallet' => Wallet::whereKey($initialWallet->id)->firstOrFail(),
                        'ledger_entry' => $entry,
                        'funding_request' => $existingRequest,
                        'pending_approval' => $existingRequest->status === WalletFundingRequest::STATUS_PENDING_ADMIN,
                        'fee_cents' => (int) $existingRequest->fee_cents,
                        'net_amount_cents' => (int) $existingRequest->net_amount_cents,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Cette clé d\'idempotence a déjà été utilisée pour une demande différente.');
            }

            $existingEntry = LedgerEntry::where('idempotency_key', $trimmedKey)->first();
            if ($existingEntry !== null) {
                if (
                    (int) $existingEntry->wallet_id === (int) $initialWallet->id
                    && $existingEntry->entry_type === LedgerEntry::TYPE_WITHDRAWAL
                    && (int) $existingEntry->debit_cents === $netAmountCents
                ) {
                    $freshWallet = Wallet::whereKey($initialWallet->id)->firstOrFail();

                    return [
                        'wallet' => $freshWallet,
                        'ledger_entry' => $existingEntry,
                        'funding_request' => null,
                        'pending_approval' => false,
                        'fee_cents' => $feeCents,
                        'net_amount_cents' => $netAmountCents,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Cette clé d\'idempotence a déjà été utilisée pour une transaction différente.');
            }

            // Lock wallets in deterministic ASC order
            $walletIds = array_values(array_unique([(int) $initialWallet->id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedUserWallet */
            $lockedUserWallet = $lockedWallets->get((int) $initialWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            // Strictly AFTER acquiring pessimistic lock: verify available balance_cents >= gross amountCents
            if ((int) $lockedUserWallet->balance_cents < $amountCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde disponible insuffisant (%s) pour exécuter le retrait de %s.',
                        Wallet::formatCents((int) $lockedUserWallet->balance_cents),
                        Wallet::formatCents($amountCents)
                    )
                );
            }

            // If rail requires manual Admin approval, create PENDING_ADMIN funding request
            if ($rail !== null && $rail->requires_admin_approval) {
                $fundingRequest = WalletFundingRequest::create([
                    'user_id' => $user->id,
                    'wallet_id' => $lockedUserWallet->id,
                    'payment_rail_id' => $rail->id,
                    'user_payment_method_id' => $userPaymentMethodId,
                    'direction' => WalletFundingRequest::DIRECTION_WITHDRAWAL,
                    'gross_amount_cents' => $amountCents,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                    'reference_code' => $iban,
                    'status' => WalletFundingRequest::STATUS_PENDING_ADMIN,
                    'idempotency_key' => $trimmedKey,
                ]);

                return [
                    'wallet' => $lockedUserWallet,
                    'ledger_entry' => null,
                    'funding_request' => $fundingRequest,
                    'pending_approval' => true,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                    'idempotent_replay' => false,
                ];
            }

            // 1. If withdrawal fee > 0, transfer fee from user wallet to Platform Treasury first
            if ($feeCents > 0 && (int) $lockedUserWallet->id !== (int) $lockedTreasuryWallet->id) {
                $feeBefore = (int) $lockedUserWallet->balance_cents;
                $feeAfter = $feeBefore - $feeCents;
                $lockedUserWallet->balance_cents = $feeAfter;
                $lockedUserWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedUserWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $user->id,
                    'entry_type' => LedgerEntry::TYPE_WITHDRAWAL_FEE_DEBIT,
                    'debit_cents' => $feeCents,
                    'credit_cents' => 0,
                    'balance_before_cents' => $feeBefore,
                    'balance_after_cents' => $feeAfter,
                    'idempotency_key' => "{$trimmedKey}:fee_debit",
                    'description' => 'Frais de traitement retrait — '.($rail?->name ?? 'Passerelle'),
                    'metadata' => [
                        'rail_code' => $rail?->code,
                        'gross_amount_cents' => $amountCents,
                    ],
                ]);

                $treasuryBefore = (int) $lockedTreasuryWallet->balance_cents;
                $treasuryAfter = $treasuryBefore + $feeCents;
                $lockedTreasuryWallet->balance_cents = $treasuryAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $user->id,
                    'entry_type' => LedgerEntry::TYPE_PLATFORM_PAYMENT_FEE_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $feeCents,
                    'balance_before_cents' => $treasuryBefore,
                    'balance_after_cents' => $treasuryAfter,
                    'idempotency_key' => "{$trimmedKey}:fee_credit",
                    'description' => "Commission passerelle retrait ({$user->gamertag}) — ".($rail?->name ?? 'Passerelle'),
                    'metadata' => [
                        'rail_code' => $rail?->code,
                        'player_gamertag' => $user->gamertag,
                    ],
                ]);
            }

            // 2. Debit net withdrawal amount (WITHDRAWAL)
            $balanceBefore = (int) $lockedUserWallet->balance_cents;
            $balanceAfter = $balanceBefore - $netAmountCents;

            $lockedUserWallet->balance_cents = $balanceAfter;
            $lockedUserWallet->save();

            $entry = LedgerEntry::create([
                'wallet_id' => $lockedUserWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_WITHDRAWAL,
                'debit_cents' => $netAmountCents,
                'credit_cents' => 0,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents' => $balanceAfter,
                'idempotency_key' => $trimmedKey,
                'description' => $rail !== null ? "{$description} ({$rail->name})" : $description,
                'metadata' => [
                    'iban' => $iban,
                    'currency' => $lockedUserWallet->currency,
                    'gamertag' => $user->gamertag,
                    'rail_code' => $rail?->code ?? 'SEPA_INSTANT',
                    'gross_amount_cents' => $amountCents,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                ],
            ]);

            $fundingRequest = null;
            if ($rail !== null) {
                $fundingRequest = WalletFundingRequest::create([
                    'user_id' => $user->id,
                    'wallet_id' => $lockedUserWallet->id,
                    'payment_rail_id' => $rail->id,
                    'user_payment_method_id' => $userPaymentMethodId,
                    'direction' => WalletFundingRequest::DIRECTION_WITHDRAWAL,
                    'gross_amount_cents' => $amountCents,
                    'fee_cents' => $feeCents,
                    'net_amount_cents' => $netAmountCents,
                    'reference_code' => $iban,
                    'status' => WalletFundingRequest::STATUS_SETTLED,
                    'idempotency_key' => $trimmedKey,
                    'processed_at' => now(),
                ]);
            }

            return [
                'wallet' => $lockedUserWallet,
                'ledger_entry' => $entry,
                'funding_request' => $fundingRequest,
                'pending_approval' => false,
                'fee_cents' => $feeCents,
                'net_amount_cents' => $netAmountCents,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Approve and settle a pending manual deposit or withdrawal request from the Admin Back-Office (/admin).
     *
     * @return array{funding_request: WalletFundingRequest, wallet: Wallet, ledger_entry: LedgerEntry}
     */
    public function approveFundingRequest(
        User $admin,
        WalletFundingRequest $fundingRequest,
        ?string $adminNotes = null
    ): array {
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($admin, $fundingRequest, $adminNotes, $treasuryWallet): array {
            /** @var WalletFundingRequest $lockedRequest */
            $lockedRequest = WalletFundingRequest::with(['user', 'paymentRail'])
                ->whereKey($fundingRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status === WalletFundingRequest::STATUS_SETTLED) {
                $existingEntry = LedgerEntry::where('idempotency_key', "{$lockedRequest->idempotency_key}:settled")->firstOrFail();

                return [
                    'funding_request' => $lockedRequest,
                    'wallet' => Wallet::whereKey($lockedRequest->wallet_id)->firstOrFail(),
                    'ledger_entry' => $existingEntry,
                    'idempotent_replay' => true,
                ];
            }

            if ($lockedRequest->status !== WalletFundingRequest::STATUS_PENDING_ADMIN) {
                throw new TournamentStateConflictException('Cette demande a déjà été traitée ou rejetée.');
            }

            $walletIds = array_values(array_unique([(int) $lockedRequest->wallet_id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedUserWallet */
            $lockedUserWallet = $lockedWallets->get((int) $lockedRequest->wallet_id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            $grossCents = (int) $lockedRequest->gross_amount_cents;
            $feeCents = (int) $lockedRequest->fee_cents;
            $netCents = (int) $lockedRequest->net_amount_cents;
            $railName = $lockedRequest->paymentRail?->name ?? 'Validation Manuelle Admin';
            $baseKey = "{$lockedRequest->idempotency_key}:settled";

            if ($lockedRequest->direction === WalletFundingRequest::DIRECTION_DEPOSIT) {
                $before = (int) $lockedUserWallet->balance_cents;
                $afterGross = $before + $grossCents;
                $lockedUserWallet->balance_cents = $afterGross;
                $lockedUserWallet->save();

                $entry = LedgerEntry::create([
                    'wallet_id' => $lockedUserWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $admin->id,
                    'entry_type' => LedgerEntry::TYPE_DEPOSIT,
                    'debit_cents' => 0,
                    'credit_cents' => $grossCents,
                    'balance_before_cents' => $before,
                    'balance_after_cents' => $afterGross,
                    'idempotency_key' => $baseKey,
                    'description' => "Dépôt validé par Admin — {$railName} ({$lockedRequest->reference_code})",
                    'metadata' => [
                        'funding_request_id' => $lockedRequest->id,
                        'approved_by_admin' => $admin->gamertag,
                        'reference_code' => $lockedRequest->reference_code,
                    ],
                ]);

                if ($feeCents > 0 && (int) $lockedUserWallet->id !== (int) $lockedTreasuryWallet->id) {
                    $feeBefore = (int) $lockedUserWallet->balance_cents;
                    $feeAfter = $feeBefore - $feeCents;
                    $lockedUserWallet->balance_cents = $feeAfter;
                    $lockedUserWallet->save();

                    LedgerEntry::create([
                        'wallet_id' => $lockedUserWallet->id,
                        'tournament_id' => null,
                        'actor_id' => $admin->id,
                        'entry_type' => LedgerEntry::TYPE_DEPOSIT_FEE_DEBIT,
                        'debit_cents' => $feeCents,
                        'credit_cents' => 0,
                        'balance_before_cents' => $feeBefore,
                        'balance_after_cents' => $feeAfter,
                        'idempotency_key' => "{$baseKey}:fee_debit",
                        'description' => "Frais dépôt manuel — {$railName}",
                        'metadata' => ['funding_request_id' => $lockedRequest->id],
                    ]);

                    $treasuryBefore = (int) $lockedTreasuryWallet->balance_cents;
                    $treasuryAfter = $treasuryBefore + $feeCents;
                    $lockedTreasuryWallet->balance_cents = $treasuryAfter;
                    $lockedTreasuryWallet->save();

                    LedgerEntry::create([
                        'wallet_id' => $lockedTreasuryWallet->id,
                        'tournament_id' => null,
                        'actor_id' => $admin->id,
                        'entry_type' => LedgerEntry::TYPE_PLATFORM_PAYMENT_FEE_CREDIT,
                        'debit_cents' => 0,
                        'credit_cents' => $feeCents,
                        'balance_before_cents' => $treasuryBefore,
                        'balance_after_cents' => $treasuryAfter,
                        'idempotency_key' => "{$baseKey}:fee_credit",
                        'description' => "Commission dépôt manuel — {$railName}",
                        'metadata' => ['funding_request_id' => $lockedRequest->id],
                    ]);
                }
            } else {
                if ((int) $lockedUserWallet->balance_cents < $grossCents) {
                    throw new InsufficientFundsException(
                        'Solde disponible de l\'opérateur insuffisant pour approuver ce retrait.'
                    );
                }

                if ($feeCents > 0 && (int) $lockedUserWallet->id !== (int) $lockedTreasuryWallet->id) {
                    $feeBefore = (int) $lockedUserWallet->balance_cents;
                    $feeAfter = $feeBefore - $feeCents;
                    $lockedUserWallet->balance_cents = $feeAfter;
                    $lockedUserWallet->save();

                    LedgerEntry::create([
                        'wallet_id' => $lockedUserWallet->id,
                        'tournament_id' => null,
                        'actor_id' => $admin->id,
                        'entry_type' => LedgerEntry::TYPE_WITHDRAWAL_FEE_DEBIT,
                        'debit_cents' => $feeCents,
                        'credit_cents' => 0,
                        'balance_before_cents' => $feeBefore,
                        'balance_after_cents' => $feeAfter,
                        'idempotency_key' => "{$baseKey}:fee_debit",
                        'description' => "Frais retrait manuel — {$railName}",
                        'metadata' => ['funding_request_id' => $lockedRequest->id],
                    ]);

                    $treasuryBefore = (int) $lockedTreasuryWallet->balance_cents;
                    $treasuryAfter = $treasuryBefore + $feeCents;
                    $lockedTreasuryWallet->balance_cents = $treasuryAfter;
                    $lockedTreasuryWallet->save();

                    LedgerEntry::create([
                        'wallet_id' => $lockedTreasuryWallet->id,
                        'tournament_id' => null,
                        'actor_id' => $admin->id,
                        'entry_type' => LedgerEntry::TYPE_PLATFORM_PAYMENT_FEE_CREDIT,
                        'debit_cents' => 0,
                        'credit_cents' => $feeCents,
                        'balance_before_cents' => $treasuryBefore,
                        'balance_after_cents' => $treasuryAfter,
                        'idempotency_key' => "{$baseKey}:fee_credit",
                        'description' => "Commission retrait manuel — {$railName}",
                        'metadata' => ['funding_request_id' => $lockedRequest->id],
                    ]);
                }

                $before = (int) $lockedUserWallet->balance_cents;
                $after = $before - $netCents;
                $lockedUserWallet->balance_cents = $after;
                $lockedUserWallet->save();

                $entry = LedgerEntry::create([
                    'wallet_id' => $lockedUserWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $admin->id,
                    'entry_type' => LedgerEntry::TYPE_WITHDRAWAL,
                    'debit_cents' => $netCents,
                    'credit_cents' => 0,
                    'balance_before_cents' => $before,
                    'balance_after_cents' => $after,
                    'idempotency_key' => $baseKey,
                    'description' => "Retrait validé par Admin — {$railName} ({$lockedRequest->reference_code})",
                    'metadata' => [
                        'funding_request_id' => $lockedRequest->id,
                        'approved_by_admin' => $admin->gamertag,
                        'reference_code' => $lockedRequest->reference_code,
                    ],
                ]);
            }

            $lockedRequest->status = WalletFundingRequest::STATUS_SETTLED;
            $lockedRequest->processed_by_admin_id = $admin->id;
            $lockedRequest->admin_notes = $adminNotes ?? 'Approuvé et réglé sur le Grand Livre par le Commandement Admin.';
            $lockedRequest->processed_at = now();
            $lockedRequest->save();

            return [
                'funding_request' => $lockedRequest->fresh(['user', 'paymentRail', 'processedByAdmin']),
                'wallet' => $lockedUserWallet,
                'ledger_entry' => $entry,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Reject a pending manual deposit or withdrawal request.
     */
    public function rejectFundingRequest(
        User $admin,
        WalletFundingRequest $fundingRequest,
        ?string $adminNotes = null
    ): WalletFundingRequest {
        return DB::transaction(function () use ($admin, $fundingRequest, $adminNotes): WalletFundingRequest {
            /** @var WalletFundingRequest $lockedRequest */
            $lockedRequest = WalletFundingRequest::whereKey($fundingRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== WalletFundingRequest::STATUS_PENDING_ADMIN) {
                throw new TournamentStateConflictException('Seule une demande en attente peut être rejetée.');
            }

            $lockedRequest->status = WalletFundingRequest::STATUS_REJECTED;
            $lockedRequest->processed_by_admin_id = $admin->id;
            $lockedRequest->admin_notes = $adminNotes ?? 'Rejeté par le Commandement Admin (référence non rapprochée).';
            $lockedRequest->processed_at = now();
            $lockedRequest->save();

            return $lockedRequest->fresh(['user', 'paymentRail', 'processedByAdmin']);
        });
    }

    /**
     * Subscribe an operator to ★ VALKYR PRIME (19,00 € / 1900 cents paid from Wallet to Platform Treasury).
     *
     * @return array{user: User, wallet: Wallet, ledger_entry: LedgerEntry, idempotent_replay: bool}
     */
    public function subscribePrime(User $user, string $idempotencyKey): array
    {
        $this->assertWalletNotFrozen($user);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire pour souscrire à VALKYR PRIME.');
        }

        $priceCents = self::PRIME_SUBSCRIPTION_PRICE_CENTS;
        $userWallet = $this->getOrCreateWallet($user);
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($user, $userWallet, $treasuryWallet, $priceCents, $trimmedKey): array {
            $existingEntry = LedgerEntry::where('idempotency_key', "{$trimmedKey}:prime_debit")->first();
            if ($existingEntry !== null) {
                if ((int) $existingEntry->wallet_id === (int) $userWallet->id) {
                    return [
                        'user' => $user->fresh(),
                        'wallet' => Wallet::findOrFail($userWallet->id),
                        'ledger_entry' => $existingEntry,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Clé d\'idempotence déjà utilisée.');
            }

            $walletIds = array_values(array_unique([(int) $userWallet->id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedUserWallet */
            $lockedUserWallet = $lockedWallets->get((int) $userWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            if ((int) $lockedUserWallet->balance_cents < $priceCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour activer le Pass ★ VALKYR PRIME (%s). Créditez d\'abord votre Wallet.',
                        Wallet::formatCents((int) $lockedUserWallet->balance_cents),
                        Wallet::formatCents($priceCents)
                    )
                );
            }

            $uBefore = (int) $lockedUserWallet->balance_cents;
            $uAfter = $uBefore - $priceCents;
            $lockedUserWallet->balance_cents = $uAfter;
            $lockedUserWallet->save();

            $debitEntry = LedgerEntry::create([
                'wallet_id' => $lockedUserWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_PRIME_SUBSCRIPTION_DEBIT,
                'debit_cents' => $priceCents,
                'credit_cents' => 0,
                'balance_before_cents' => $uBefore,
                'balance_after_cents' => $uAfter,
                'idempotency_key' => "{$trimmedKey}:prime_debit",
                'description' => 'Activation Pass Membre ★ VALKYR PRIME (30 Jours — 0 % Frais Rails)',
                'metadata' => ['gamertag' => $user->gamertag],
            ]);

            if ((int) $lockedTreasuryWallet->id === (int) $lockedUserWallet->id) {
                $lockedTreasuryWallet = $lockedUserWallet;
            }

            $tBefore = (int) $lockedTreasuryWallet->balance_cents;
            $tAfter = $tBefore + $priceCents;
            $lockedTreasuryWallet->balance_cents = $tAfter;
            $lockedTreasuryWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedTreasuryWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_PRIME_SUBSCRIPTION_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $priceCents,
                'balance_before_cents' => $tBefore,
                'balance_after_cents' => $tAfter,
                'idempotency_key' => "{$trimmedKey}:prime_credit",
                'description' => "Revenu Abonnement ★ VALKYR PRIME ({$user->gamertag})",
                'metadata' => ['user_id' => $user->id, 'gamertag' => $user->gamertag],
            ]);

            $user->is_prime = true;
            $user->prime_expires_at = now()->addDays(30);
            $user->elo_rating = (int) $user->elo_rating + 50;
            $user->save();

            return [
                'user' => $user->fresh(),
                'wallet' => $lockedUserWallet,
                'ledger_entry' => $debitEntry,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Boost a tournament to ★ FEATURED status (25,00 € / 2500 cents paid from Organizer/Admin Wallet to Platform Treasury).
     *
     * @return array{tournament: Tournament, wallet: Wallet, ledger_entry: LedgerEntry, idempotent_replay: bool}
     */
    public function boostTournament(User $actor, Tournament $tournament, string $idempotencyKey): array
    {
        $this->assertWalletNotFrozen($actor);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour le boost d\'arène.');
        }

        $priceCents = self::ARENA_BOOST_PRICE_CENTS;
        $actorWallet = $this->getOrCreateWallet($actor);
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($actor, $tournament, $actorWallet, $treasuryWallet, $priceCents, $trimmedKey): array {
            $existingEntry = LedgerEntry::where('idempotency_key', "{$trimmedKey}:boost_debit")->first();
            if ($existingEntry !== null) {
                return [
                    'tournament' => Tournament::with(['organizer', 'registrations.user'])->findOrFail($tournament->id),
                    'wallet' => Wallet::findOrFail($actorWallet->id),
                    'ledger_entry' => $existingEntry,
                    'idempotent_replay' => true,
                ];
            }

            $lockedTournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            $walletIds = array_values(array_unique([(int) $actorWallet->id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedActorWallet */
            $lockedActorWallet = $lockedWallets->get((int) $actorWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            if ((int) $lockedActorWallet->balance_cents < $priceCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour activer le Boost ★ FEATURED (%s).',
                        Wallet::formatCents((int) $lockedActorWallet->balance_cents),
                        Wallet::formatCents($priceCents)
                    )
                );
            }

            $aBefore = (int) $lockedActorWallet->balance_cents;
            $aAfter = $aBefore - $priceCents;
            $lockedActorWallet->balance_cents = $aAfter;
            $lockedActorWallet->save();

            $debitEntry = LedgerEntry::create([
                'wallet_id' => $lockedActorWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_ARENA_BOOST_DEBIT,
                'debit_cents' => $priceCents,
                'credit_cents' => 0,
                'balance_before_cents' => $aBefore,
                'balance_after_cents' => $aAfter,
                'idempotency_key' => "{$trimmedKey}:boost_debit",
                'description' => "Boost Visibilité ★ FEATURED — {$lockedTournament->title}",
                'metadata' => ['tournament_slug' => $lockedTournament->slug],
            ]);

            if ((int) $lockedTreasuryWallet->id === (int) $lockedActorWallet->id) {
                $lockedTreasuryWallet = $lockedActorWallet;
            }

            $tBefore = (int) $lockedTreasuryWallet->balance_cents;
            $tAfter = $tBefore + $priceCents;
            $lockedTreasuryWallet->balance_cents = $tAfter;
            $lockedTreasuryWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedTreasuryWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_ARENA_BOOST_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $priceCents,
                'balance_before_cents' => $tBefore,
                'balance_after_cents' => $tAfter,
                'idempotency_key' => "{$trimmedKey}:boost_credit",
                'description' => "Revenu Boost Arène ★ FEATURED ({$actor->gamertag}) — {$lockedTournament->title}",
                'metadata' => ['tournament_id' => $lockedTournament->id],
            ]);

            $lockedTournament->is_featured = true;
            $lockedTournament->boost_paid_cents = (int) ($lockedTournament->getAttributes()['boost_paid_cents'] ?? 0) + $priceCents;
            $lockedTournament->save();

            return [
                'tournament' => $lockedTournament->fresh(['organizer', 'registrations.user']),
                'wallet' => $lockedActorWallet,
                'ledger_entry' => $debitEntry,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Inject sponsored prize pool funds from an Organizer/Admin Wallet into the Tournament Escrow Vault.
     *
     * @return array{tournament: Tournament, wallet: Wallet, ledger_entry: LedgerEntry, idempotent_replay: bool}
     */
    public function sponsorTournament(
        User $actor,
        Tournament $tournament,
        int $amountCents,
        string $idempotencyKey
    ): array {
        $this->assertWalletNotFrozen($actor);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour le sponsoring.');
        }

        if ($amountCents < 500 || $amountCents > 50_000_000) {
            throw new InvalidFinancialOperationException('La dotation sponsorisée doit être comprise entre 5,00 € (500 centimes) et 500 000,00 €.');
        }

        $actorWallet = $this->getOrCreateWallet($actor);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];

        return DB::transaction(function () use ($actor, $tournament, $actorWallet, $escrowWallet, $amountCents, $trimmedKey): array {
            $existingEntry = LedgerEntry::where('idempotency_key', "{$trimmedKey}:sponsor_debit")->first();
            if ($existingEntry !== null) {
                return [
                    'tournament' => Tournament::with(['organizer', 'registrations.user'])->findOrFail($tournament->id),
                    'wallet' => Wallet::findOrFail($actorWallet->id),
                    'ledger_entry' => $existingEntry,
                    'idempotent_replay' => true,
                ];
            }

            $lockedTournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedTournament->status, [Tournament::STATUS_SETTLED, Tournament::STATUS_CANCELLED], true)) {
                throw new TournamentStateConflictException('Impossible d\'injecter une dotation dans un tournoi déjà clôturé ou annulé.');
            }

            $walletIds = array_values(array_unique([(int) $actorWallet->id, (int) $escrowWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedActorWallet */
            $lockedActorWallet = $lockedWallets->get((int) $actorWallet->id);
            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);

            if ((int) $lockedActorWallet->balance_cents < $amountCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour injecter la dotation sponsorisée de %s.',
                        Wallet::formatCents((int) $lockedActorWallet->balance_cents),
                        Wallet::formatCents($amountCents)
                    )
                );
            }

            $aBefore = (int) $lockedActorWallet->balance_cents;
            $aAfter = $aBefore - $amountCents;
            $lockedActorWallet->balance_cents = $aAfter;
            $lockedActorWallet->save();

            $debitEntry = LedgerEntry::create([
                'wallet_id' => $lockedActorWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_SPONSOR_POOL_DEBIT,
                'debit_cents' => $amountCents,
                'credit_cents' => 0,
                'balance_before_cents' => $aBefore,
                'balance_after_cents' => $aAfter,
                'idempotency_key' => "{$trimmedKey}:sponsor_debit",
                'description' => "Dotation Sponsorisée injectée — {$lockedTournament->title}",
                'metadata' => ['tournament_slug' => $lockedTournament->slug],
            ]);

            if ((int) $lockedEscrowWallet->id === (int) $lockedActorWallet->id) {
                $lockedEscrowWallet = $lockedActorWallet;
            }

            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore + $amountCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_SPONSOR_POOL_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $amountCents,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:sponsor_credit",
                'description' => "Réception Dotation Sponsorisée ({$actor->gamertag}) — {$lockedTournament->title}",
                'metadata' => ['sponsor_gamertag' => $actor->gamertag],
            ]);

            $lockedTournament->escrow_pool_cents = (int) $lockedTournament->escrow_pool_cents + $amountCents;
            $lockedTournament->sponsored_guarantee_cents = (int) $lockedTournament->sponsored_guarantee_cents + $amountCents;
            $lockedTournament->save();

            return [
                'tournament' => $lockedTournament->fresh(['organizer', 'registrations.user']),
                'wallet' => $lockedActorWallet,
                'ledger_entry' => $debitEntry,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Atomically lock player & escrow wallets in ascending ID order, verify balance after lock,
     * and move entry_fee_cents into escrow via symmetric double-entry ledger records.
     *
     * @return array{tournament: Tournament, registration: TournamentRegistration, wallet: Wallet, idempotent_replay: bool}
     */
    public function registerForTournament(
        User $player,
        Tournament $tournament,
        string $idempotencyKey,
        ?string $loadoutClass = null
    ): array {
        $this->assertWalletNotFrozen($player);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire pour l\'inscription au tournoi.');
        }

        $chosenClass = strtoupper(trim($loadoutClass ?? $player->preferred_class ?? 'VANGUARD'));
        if (! in_array($chosenClass, User::ALLOWED_CLASSES, true)) {
            throw new InvalidFinancialOperationException('Archétype de classe invalide.');
        }

        $playerWallet = $this->getOrCreateWallet($player);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];

        return DB::transaction(function () use ($player, $tournament, $playerWallet, $escrowWallet, $trimmedKey, $chosenClass): array {
            $existingRegistration = TournamentRegistration::where('idempotency_key', $trimmedKey)->first();
            if ($existingRegistration !== null) {
                if (
                    (int) $existingRegistration->user_id === (int) $player->id
                    && (int) $existingRegistration->tournament_id === (int) $tournament->id
                ) {
                    return [
                        'tournament' => Tournament::with(['registrations.user', 'organizer'])->findOrFail($tournament->id),
                        'registration' => $existingRegistration,
                        'wallet' => Wallet::findOrFail($playerWallet->id),
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Clé d\'idempotence déjà associée à une autre inscription.');
            }

            if (LedgerEntry::whereIn('idempotency_key', [$trimmedKey, "{$trimmedKey}:player_debit", "{$trimmedKey}:escrow_credit"])->exists()) {
                throw new IdempotencyConflictException('Clé d\'idempotence déjà utilisée dans le grand livre.');
            }

            // 1. Pessimistic lock on the tournament row
            $lockedTournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            // 2. Deterministic Pessimistic Lock on involved wallets ordered strictly by ID ASC
            $walletIds = array_values(array_unique([(int) $playerWallet->id, (int) $escrowWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedPlayerWallet */
            $lockedPlayerWallet = $lockedWallets->get((int) $playerWallet->id);
            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);

            // 3. Strictly AFTER locks: check duplicate registration, tournament state, capacity, and sufficient funds
            $alreadyRegistered = TournamentRegistration::where('tournament_id', $lockedTournament->id)
                ->where('user_id', $player->id)
                ->exists();

            if ($alreadyRegistered) {
                throw new TournamentStateConflictException('Vous êtes déjà inscrit à ce tournoi.');
            }

            if ($lockedTournament->status !== Tournament::STATUS_OPEN) {
                throw new TournamentStateConflictException('Les inscriptions pour ce tournoi sont fermées (statut : '.$lockedTournament->status.').');
            }

            if ((int) $lockedTournament->registered_count >= (int) $lockedTournament->max_participants) {
                throw new TournamentStateConflictException('Ce tournoi a atteint sa capacité maximale.');
            }

            $entryFeeCents = (int) $lockedTournament->entry_fee_cents;
            if ($entryFeeCents <= 0) {
                throw new InvalidFinancialOperationException('Montant de Buy-in du tournoi invalide.');
            }

            if ((int) $lockedPlayerWallet->balance_cents < $entryFeeCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde disponible insuffisant (%s) pour couvrir le Buy-in requis de %s.',
                        Wallet::formatCents((int) $lockedPlayerWallet->balance_cents),
                        Wallet::formatCents($entryFeeCents)
                    )
                );
            }

            // 4. Double-entry accounting: Debit Player Wallet -> Credit Escrow Vault Wallet
            $playerBefore = (int) $lockedPlayerWallet->balance_cents;
            $playerAfter = $playerBefore - $entryFeeCents;
            $lockedPlayerWallet->balance_cents = $playerAfter;
            $lockedPlayerWallet->locked_escrow_cents = (int) $lockedPlayerWallet->locked_escrow_cents + $entryFeeCents;
            $lockedPlayerWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedPlayerWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $player->id,
                'entry_type' => LedgerEntry::TYPE_BUY_IN_ESCROW_DEBIT,
                'debit_cents' => $entryFeeCents,
                'credit_cents' => 0,
                'balance_before_cents' => $playerBefore,
                'balance_after_cents' => $playerAfter,
                'idempotency_key' => "{$trimmedKey}:player_debit",
                'description' => "Verrouillage séquestre Buy-in — {$lockedTournament->title}",
                'metadata' => [
                    'tournament_slug' => $lockedTournament->slug,
                    'loadout_class' => $chosenClass,
                ],
            ]);

            if ((int) $lockedEscrowWallet->id === (int) $lockedPlayerWallet->id) {
                $lockedEscrowWallet = $lockedPlayerWallet;
            }

            $escrowBefore = (int) $lockedEscrowWallet->balance_cents;
            $escrowAfter = $escrowBefore + $entryFeeCents;
            $lockedEscrowWallet->balance_cents = $escrowAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $player->id,
                'entry_type' => LedgerEntry::TYPE_BUY_IN_ESCROW_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $entryFeeCents,
                'balance_before_cents' => $escrowBefore,
                'balance_after_cents' => $escrowAfter,
                'idempotency_key' => "{$trimmedKey}:escrow_credit",
                'description' => "Séquestre reçu ({$player->gamertag}) — {$lockedTournament->title}",
                'metadata' => [
                    'player_id' => $player->id,
                    'player_gamertag' => $player->gamertag,
                ],
            ]);

            // 5. Update tournament pool and registration count
            $lockedTournament->escrow_pool_cents = (int) $lockedTournament->escrow_pool_cents + $entryFeeCents;
            $lockedTournament->registered_count = (int) $lockedTournament->registered_count + 1;

            if ((int) $lockedTournament->registered_count >= (int) $lockedTournament->max_participants) {
                $lockedTournament->status = Tournament::STATUS_LOCKED;
            }

            $lockedTournament->save();

            $registration = TournamentRegistration::create([
                'tournament_id' => $lockedTournament->id,
                'user_id' => $player->id,
                'loadout_class' => $chosenClass,
                'paid_entry_fee_cents' => $entryFeeCents,
                'prize_won_cents' => 0,
                'status' => TournamentRegistration::STATUS_REGISTERED,
                'idempotency_key' => $trimmedKey,
            ]);

            return [
                'tournament' => $lockedTournament->fresh(['registrations.user', 'organizer']),
                'registration' => $registration,
                'wallet' => $lockedPlayerWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Atomically settle a tournament:
     * - Deduct platform rake (default 800 bps = 8%), splitting between Platform Treasury and Organizer RevShare (if organizer_share_bps > 0)
     * - Distribute net pool: 60% (+ integer remainder) to 1st, 30% to 2nd, 10% to 3rd (or 70%/30% if 2 players)
     * - Verify strict monetary conservation: treasury_rake + organizer_rake + 1st + 2nd + 3rd === totalPool
     *
     * @return array{
     *     tournament: Tournament,
     *     breakdown: array{
     *         total_pool_cents: int,
     *         platform_rake_cents: int,
     *         organizer_rake_cents: int,
     *         net_prize_pool_cents: int,
     *         first_prize_cents: int,
     *         second_prize_cents: int,
     *         third_prize_cents: int
     *     },
     *     idempotent_replay: bool
     * }
     */
    public function settleTournament(
        User $actor,
        Tournament $tournament,
        int $firstPlaceUserId,
        int $secondPlaceUserId,
        ?int $thirdPlaceUserId,
        string $idempotencyKey
    ): array {
        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire pour clôturer le tournoi.');
        }

        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use (
            $actor,
            $tournament,
            $firstPlaceUserId,
            $secondPlaceUserId,
            $thirdPlaceUserId,
            $trimmedKey,
            $escrowWallet,
            $treasuryWallet
        ): array {
            $lockedTournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            if ($lockedTournament->status === Tournament::STATUS_SETTLED) {
                if (
                    $lockedTournament->settlement_idempotency_key === $trimmedKey
                    && (int) $lockedTournament->first_place_user_id === $firstPlaceUserId
                    && (int) $lockedTournament->second_place_user_id === $secondPlaceUserId
                    && (int) ($lockedTournament->third_place_user_id ?? 0) === (int) ($thirdPlaceUserId ?? 0)
                ) {
                    $regs = TournamentRegistration::where('tournament_id', $lockedTournament->id)->get()->keyBy('user_id');

                    return [
                        'tournament' => $lockedTournament->fresh(['registrations.user', 'organizer', 'firstPlace', 'secondPlace', 'thirdPlace']),
                        'breakdown' => [
                            'total_pool_cents' => (int) $lockedTournament->escrow_pool_cents,
                            'platform_rake_cents' => (int) $lockedTournament->platform_rake_cents,
                            'organizer_rake_cents' => (int) $lockedTournament->organizer_rake_cents,
                            'net_prize_pool_cents' => (int) $lockedTournament->net_prize_pool_cents,
                            'first_prize_cents' => (int) ($regs->get($firstPlaceUserId)?->prize_won_cents ?? 0),
                            'second_prize_cents' => (int) ($regs->get($secondPlaceUserId)?->prize_won_cents ?? 0),
                            'third_prize_cents' => $thirdPlaceUserId !== null ? (int) ($regs->get($thirdPlaceUserId)?->prize_won_cents ?? 0) : 0,
                        ],
                        'idempotent_replay' => true,
                    ];
                }

                throw new TournamentStateConflictException('Ce tournoi a déjà été clôturé et son cashprize a déjà été distribué.');
            }

            if ($lockedTournament->status === Tournament::STATUS_CANCELLED) {
                throw new TournamentStateConflictException('Impossible de clôturer un tournoi annulé.');
            }

            if (! in_array($lockedTournament->status, [Tournament::STATUS_OPEN, Tournament::STATUS_LOCKED, Tournament::STATUS_IN_PROGRESS], true)) {
                throw new TournamentStateConflictException('Statut du tournoi incompatible avec la clôture.');
            }

            if (
                Tournament::where('settlement_idempotency_key', $trimmedKey)->exists()
                || LedgerEntry::where('idempotency_key', "{$trimmedKey}:escrow_release")->exists()
            ) {
                throw new IdempotencyConflictException('Cette clé d\'idempotence de règlement a déjà été utilisée.');
            }

            // Validate distinct winners
            $winnerIds = [$firstPlaceUserId, $secondPlaceUserId];
            if ($thirdPlaceUserId !== null) {
                $winnerIds[] = $thirdPlaceUserId;
            }

            if (count($winnerIds) !== count(array_unique($winnerIds))) {
                throw new InvalidFinancialOperationException('Les gagnants des 1re, 2e et 3e places doivent être des joueurs strictement distincts.');
            }

            $registrations = TournamentRegistration::where('tournament_id', $lockedTournament->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            foreach ($winnerIds as $winnerId) {
                $reg = $registrations->get($winnerId);
                if ($reg === null || $reg->status !== TournamentRegistration::STATUS_REGISTERED) {
                    throw new InvalidFinancialOperationException("Le joueur ID #{$winnerId} n'est pas inscrit à ce tournoi.");
                }
            }

            $totalPool = (int) $lockedTournament->escrow_pool_cents;
            if ($totalPool <= 0) {
                throw new InvalidFinancialOperationException('Le pool séquestré de ce tournoi est nul.');
            }

            $organizerUser = User::find($lockedTournament->organizer_id);
            $organizerWallet = $organizerUser !== null ? $this->getOrCreateWallet($organizerUser) : null;

            $lockedPredictions = TournamentPrediction::where('tournament_id', $lockedTournament->id)
                ->where('status', TournamentPrediction::STATUS_LOCKED)
                ->lockForUpdate()
                ->get();

            // Ensure all registered participants and predictors have wallets, then lock all wallets in deterministic ASC order
            $participantUserIds = $registrations->keys()->map(fn ($id): int => (int) $id)->all();
            $predictorUserIds = $lockedPredictions->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
            $allInvolvedUserIds = array_values(array_unique(array_merge($participantUserIds, $predictorUserIds)));

            foreach ($allInvolvedUserIds as $uid) {
                Wallet::firstOrCreate(
                    ['user_id' => $uid],
                    [
                        'balance_cents' => 0,
                        'locked_escrow_cents' => 0,
                        'lifetime_won_cents' => 0,
                        'currency' => 'EUR',
                    ]
                );
            }

            $participantWallets = Wallet::whereIn('user_id', $allInvolvedUserIds)->get()->keyBy('user_id');

            $allWalletIds = [
                (int) $escrowWallet->id,
                (int) $treasuryWallet->id,
            ];
            if ($organizerWallet !== null) {
                $allWalletIds[] = (int) $organizerWallet->id;
            }
            foreach ($participantWallets as $pw) {
                $allWalletIds[] = (int) $pw->id;
            }
            $allWalletIds = array_values(array_unique($allWalletIds));
            sort($allWalletIds);

            $lockedWalletsById = Wallet::whereIn('id', $allWalletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWalletsById->get((int) $escrowWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWalletsById->get((int) $treasuryWallet->id);

            if ((int) $lockedEscrowWallet->balance_cents < $totalPool) {
                throw new InsufficientFundsException('Solde du compte séquestre système insuffisant pour couvrir la distribution.');
            }

            // Server-side integer math: Total Rake (default 800 bps = 8%) + Organizer RevShare + 60/30/10 net distribution
            $rakeBps = (int) ($lockedTournament->rake_bps ?: Tournament::DEFAULT_RAKE_BPS);
            $totalRakeCents = intdiv($totalPool * $rakeBps, 10000);

            $orgShareBps = max(0, min($rakeBps, (int) $lockedTournament->organizer_share_bps));
            if ($orgShareBps > 0 && $organizerUser !== null && $organizerUser->isOrganizer() && $organizerWallet !== null) {
                $organizerRakeCents = intdiv($totalPool * $orgShareBps, 10000);
                $treasuryRakeCents = $totalRakeCents - $organizerRakeCents;
            } else {
                $organizerRakeCents = 0;
                $treasuryRakeCents = $totalRakeCents;
            }

            $netPoolCents = $totalPool - $totalRakeCents;

            if ($thirdPlaceUserId !== null) {
                $secondPrize = intdiv($netPoolCents * 3000, 10000);
                $thirdPrize = intdiv($netPoolCents * 1000, 10000);
                $firstPrize = $netPoolCents - $secondPrize - $thirdPrize;
            } else {
                $secondPrize = intdiv($netPoolCents * 3000, 10000);
                $thirdPrize = 0;
                $firstPrize = $netPoolCents - $secondPrize;
            }

            if ($treasuryRakeCents + $organizerRakeCents + $firstPrize + $secondPrize + $thirdPrize !== $totalPool) {
                throw new DomainException('Violation de l\'invariant de conservation monétaire lors de la clôture.');
            }

            // 1. Debit Escrow Vault for the full escrow pool
            $escrowBefore = (int) $lockedEscrowWallet->balance_cents;
            $escrowAfter = $escrowBefore - $totalPool;
            $lockedEscrowWallet->balance_cents = $escrowAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_ESCROW_RELEASE_DEBIT,
                'debit_cents' => $totalPool,
                'credit_cents' => 0,
                'balance_before_cents' => $escrowBefore,
                'balance_after_cents' => $escrowAfter,
                'idempotency_key' => "{$trimmedKey}:escrow_release",
                'description' => "Libération intégrale du séquestre — {$lockedTournament->title}",
                'metadata' => [
                    'total_pool_cents' => $totalPool,
                    'platform_rake_cents' => $treasuryRakeCents,
                    'organizer_rake_cents' => $organizerRakeCents,
                    'net_prize_pool_cents' => $netPoolCents,
                ],
            ]);

            // 2. Credit Platform Treasury with its net commission
            if ($treasuryRakeCents > 0) {
                $treasuryBefore = (int) $lockedTreasuryWallet->balance_cents;
                $treasuryAfter = $treasuryBefore + $treasuryRakeCents;
                $lockedTreasuryWallet->balance_cents = $treasuryAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_PLATFORM_RAKE_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $treasuryRakeCents,
                    'balance_before_cents' => $treasuryBefore,
                    'balance_after_cents' => $treasuryAfter,
                    'idempotency_key' => "{$trimmedKey}:platform_rake",
                    'description' => "Commission plateforme ({$rakeBps} bps) — {$lockedTournament->title}",
                    'metadata' => [
                        'rake_bps' => $rakeBps,
                        'organizer_share_bps' => $orgShareBps,
                        'total_pool_cents' => $totalPool,
                    ],
                ]);
            }

            // 2b. Credit Organizer Wallet with Organizer Revenue Share if applicable
            if ($organizerRakeCents > 0 && $organizerWallet !== null) {
                /** @var Wallet $lockedOrgWallet */
                $lockedOrgWallet = $lockedWalletsById->get((int) $organizerWallet->id);

                $orgBefore = (int) $lockedOrgWallet->balance_cents;
                $orgAfter = $orgBefore + $organizerRakeCents;
                $lockedOrgWallet->balance_cents = $orgAfter;
                $lockedOrgWallet->lifetime_won_cents = (int) $lockedOrgWallet->lifetime_won_cents + $organizerRakeCents;
                $lockedOrgWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedOrgWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_ORGANIZER_RAKE_SHARE_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $organizerRakeCents,
                    'balance_before_cents' => $orgBefore,
                    'balance_after_cents' => $orgAfter,
                    'idempotency_key' => "{$trimmedKey}:organizer_rake_share",
                    'description' => "Commission Organisateur RevShare ({$orgShareBps} bps) — {$lockedTournament->title}",
                    'metadata' => [
                        'organizer_id' => $lockedTournament->organizer_id,
                        'organizer_share_bps' => $orgShareBps,
                    ],
                ]);
            }

            // Helper closure to credit a winner's locked wallet
            $creditWinner = function (int $winnerUserId, int $prizeCents, string $entryType, string $suffix, string $rankLabel) use (
                $participantWallets,
                $lockedWalletsById,
                $lockedTournament,
                $actor,
                $trimmedKey
            ): void {
                if ($prizeCents <= 0) {
                    return;
                }

                $walletId = (int) $participantWallets->get($winnerUserId)->id;
                /** @var Wallet $winnerWallet */
                $winnerWallet = $lockedWalletsById->get($walletId);

                $before = (int) $winnerWallet->balance_cents;
                $after = $before + $prizeCents;
                $winnerWallet->balance_cents = $after;
                $winnerWallet->lifetime_won_cents = (int) $winnerWallet->lifetime_won_cents + $prizeCents;
                $winnerWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $winnerWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => $entryType,
                    'debit_cents' => 0,
                    'credit_cents' => $prizeCents,
                    'balance_before_cents' => $before,
                    'balance_after_cents' => $after,
                    'idempotency_key' => "{$trimmedKey}:{$suffix}",
                    'description' => "Gain Cashprize {$rankLabel} — {$lockedTournament->title}",
                    'metadata' => [
                        'rank' => $rankLabel,
                        'tournament_slug' => $lockedTournament->slug,
                    ],
                ]);
            };

            // 3. Credit 1st, 2nd, and 3rd place winners
            $creditWinner($firstPlaceUserId, $firstPrize, LedgerEntry::TYPE_PRIZE_PAYOUT_1ST, 'payout_1st', '1re Place (60 %)');
            $creditWinner($secondPlaceUserId, $secondPrize, LedgerEntry::TYPE_PRIZE_PAYOUT_2ND, 'payout_2nd', '2e Place (30 %)');
            if ($thirdPlaceUserId !== null && $thirdPrize > 0) {
                $creditWinner($thirdPlaceUserId, $thirdPrize, LedgerEntry::TYPE_PRIZE_PAYOUT_3RD, 'payout_3rd', '3e Place (10 %)');
            }

            // 4. Release locked_escrow_cents on all participants and update registration statuses
            foreach ($registrations as $uid => $registration) {
                $walletId = (int) $participantWallets->get((int) $uid)->id;
                /** @var Wallet $pWallet */
                $pWallet = $lockedWalletsById->get($walletId);
                $pWallet->locked_escrow_cents = max(0, (int) $pWallet->locked_escrow_cents - (int) $registration->paid_entry_fee_cents);
                $pWallet->save();

                if ((int) $uid === $firstPlaceUserId) {
                    $registration->status = TournamentRegistration::STATUS_WINNER_1ST;
                    $registration->prize_won_cents = $firstPrize;
                } elseif ((int) $uid === $secondPlaceUserId) {
                    $registration->status = TournamentRegistration::STATUS_WINNER_2ND;
                    $registration->prize_won_cents = $secondPrize;
                } elseif ($thirdPlaceUserId !== null && (int) $uid === $thirdPlaceUserId) {
                    $registration->status = TournamentRegistration::STATUS_WINNER_3RD;
                    $registration->prize_won_cents = $thirdPrize;
                } else {
                    $registration->status = TournamentRegistration::STATUS_ELIMINATED;
                    $registration->prize_won_cents = 0;
                }

                $registration->save();
            }

            // 4b. Settle Spectator Prediction Pool (10% Rake to SYS_TREASURY + 90% distributed pro-rata to winning predictors)
            $predictionPoolCents = 0;
            $predRakeCents = 0;
            if ($lockedPredictions->isNotEmpty()) {
                $predictionPoolCents = (int) $lockedPredictions->sum('stake_cents');
                if ($predictionPoolCents > 0 && (int) $lockedEscrowWallet->balance_cents >= $predictionPoolCents) {
                    $predEscrowBefore = (int) $lockedEscrowWallet->balance_cents;
                    $predEscrowAfter = $predEscrowBefore - $predictionPoolCents;
                    $lockedEscrowWallet->balance_cents = $predEscrowAfter;
                    $lockedEscrowWallet->save();

                    LedgerEntry::create([
                        'wallet_id' => $lockedEscrowWallet->id,
                        'tournament_id' => $lockedTournament->id,
                        'actor_id' => $actor->id,
                        'entry_type' => LedgerEntry::TYPE_ESCROW_RELEASE_DEBIT,
                        'debit_cents' => $predictionPoolCents,
                        'credit_cents' => 0,
                        'balance_before_cents' => $predEscrowBefore,
                        'balance_after_cents' => $predEscrowAfter,
                        'idempotency_key' => "{$trimmedKey}:pred_escrow_release",
                        'description' => "Libération Pool Pronostics Spectateurs — {$lockedTournament->title}",
                    ]);

                    // Release locked_escrow_cents for each predictor
                    foreach ($lockedPredictions as $pred) {
                        $predWalletId = (int) $participantWallets->get((int) $pred->user_id)->id;
                        /** @var Wallet $predWallet */
                        $predWallet = $lockedWalletsById->get($predWalletId);
                        $predWallet->locked_escrow_cents = max(0, (int) $predWallet->locked_escrow_cents - (int) $pred->stake_cents);
                        $predWallet->save();
                    }

                    $predRakeCents = intdiv($predictionPoolCents * TournamentPrediction::PREDICTION_RAKE_BPS, 10000);
                    $predNetPoolCents = $predictionPoolCents - $predRakeCents;

                    if ($predRakeCents > 0) {
                        $tBefore = (int) $lockedTreasuryWallet->balance_cents;
                        $tAfter = $tBefore + $predRakeCents;
                        $lockedTreasuryWallet->balance_cents = $tAfter;
                        $lockedTreasuryWallet->save();

                        LedgerEntry::create([
                            'wallet_id' => $lockedTreasuryWallet->id,
                            'tournament_id' => $lockedTournament->id,
                            'actor_id' => $actor->id,
                            'entry_type' => LedgerEntry::TYPE_PLATFORM_RAKE_CREDIT,
                            'debit_cents' => 0,
                            'credit_cents' => $predRakeCents,
                            'balance_before_cents' => $tBefore,
                            'balance_after_cents' => $tAfter,
                            'idempotency_key' => "{$trimmedKey}:pred_platform_rake",
                            'description' => "Commission Pool Pronostics (10 %) — {$lockedTournament->title}",
                        ]);
                    }

                    $winningPredictions = $lockedPredictions
                        ->filter(fn (TournamentPrediction $p): bool => (int) $p->predicted_winner_user_id === $firstPlaceUserId)
                        ->values();

                    if ($winningPredictions->isNotEmpty()) {
                        $totalWinningStake = (int) $winningPredictions->sum('stake_cents');
                        $allocated = 0;
                        $shares = [];
                        foreach ($winningPredictions as $idx => $winPred) {
                            $share = intdiv($predNetPoolCents * (int) $winPred->stake_cents, $totalWinningStake);
                            $shares[$idx] = $share;
                            $allocated += $share;
                        }
                        $shares[0] += ($predNetPoolCents - $allocated);

                        foreach ($winningPredictions as $idx => $winPred) {
                            $payoutCents = $shares[$idx];
                            $winWalletId = (int) $participantWallets->get((int) $winPred->user_id)->id;
                            /** @var Wallet $winWallet */
                            $winWallet = $lockedWalletsById->get($winWalletId);

                            if ($payoutCents > 0) {
                                $wBefore = (int) $winWallet->balance_cents;
                                $wAfter = $wBefore + $payoutCents;
                                $winWallet->balance_cents = $wAfter;
                                $winWallet->lifetime_won_cents = (int) $winWallet->lifetime_won_cents + $payoutCents;
                                $winWallet->save();

                                LedgerEntry::create([
                                    'wallet_id' => $winWallet->id,
                                    'tournament_id' => $lockedTournament->id,
                                    'actor_id' => $actor->id,
                                    'entry_type' => LedgerEntry::TYPE_PREDICTION_PAYOUT_CREDIT,
                                    'debit_cents' => 0,
                                    'credit_cents' => $payoutCents,
                                    'balance_before_cents' => $wBefore,
                                    'balance_after_cents' => $wAfter,
                                    'idempotency_key' => "{$trimmedKey}:pred_payout:{$winPred->id}",
                                    'description' => "Gain Pronostic Spectateur (90 % Net Pool) — {$lockedTournament->title}",
                                ]);
                            }

                            $winPred->payout_cents = $payoutCents;
                            $winPred->status = TournamentPrediction::STATUS_WON;
                            $winPred->save();
                        }

                        foreach ($lockedPredictions as $pred) {
                            if ((int) $pred->predicted_winner_user_id !== $firstPlaceUserId) {
                                $pred->payout_cents = 0;
                                $pred->status = TournamentPrediction::STATUS_LOST;
                                $pred->save();
                            }
                        }
                    } else {
                        // Nobody guessed the winner: refund 90% net pool pro-rata to predictors
                        $allocated = 0;
                        $shares = [];
                        $allPreds = $lockedPredictions->values();
                        foreach ($allPreds as $idx => $pred) {
                            $share = intdiv($predNetPoolCents * (int) $pred->stake_cents, $predictionPoolCents);
                            $shares[$idx] = $share;
                            $allocated += $share;
                        }
                        $shares[0] += ($predNetPoolCents - $allocated);

                        foreach ($allPreds as $idx => $pred) {
                            $refundShare = $shares[$idx];
                            $pWalletId = (int) $participantWallets->get((int) $pred->user_id)->id;
                            /** @var Wallet $pWallet */
                            $pWallet = $lockedWalletsById->get($pWalletId);

                            if ($refundShare > 0) {
                                $wBefore = (int) $pWallet->balance_cents;
                                $wAfter = $wBefore + $refundShare;
                                $pWallet->balance_cents = $wAfter;
                                $pWallet->save();

                                LedgerEntry::create([
                                    'wallet_id' => $pWallet->id,
                                    'tournament_id' => $lockedTournament->id,
                                    'actor_id' => $actor->id,
                                    'entry_type' => LedgerEntry::TYPE_PREDICTION_PAYOUT_CREDIT,
                                    'debit_cents' => 0,
                                    'credit_cents' => $refundShare,
                                    'balance_before_cents' => $wBefore,
                                    'balance_after_cents' => $wAfter,
                                    'idempotency_key' => "{$trimmedKey}:pred_refund:{$pred->id}",
                                    'description' => "Remboursement Pool Pronostics (aucun gagnant) — {$lockedTournament->title}",
                                ]);
                            }

                            $pred->payout_cents = $refundShare;
                            $pred->status = TournamentPrediction::STATUS_REFUNDED;
                            $pred->save();
                        }
                    }
                }
            }

            // 5. Update ELO ratings for podium players
            User::whereKey($firstPlaceUserId)->increment('elo_rating', 25);
            User::whereKey($secondPlaceUserId)->increment('elo_rating', 10);
            if ($thirdPlaceUserId !== null) {
                User::whereKey($thirdPlaceUserId)->increment('elo_rating', 5);
            }

            // 6. Finalize tournament record
            $lockedTournament->status = Tournament::STATUS_SETTLED;
            $lockedTournament->platform_rake_cents = $treasuryRakeCents;
            $lockedTournament->organizer_rake_cents = $organizerRakeCents;
            $lockedTournament->net_prize_pool_cents = $netPoolCents;
            $lockedTournament->first_place_user_id = $firstPlaceUserId;
            $lockedTournament->second_place_user_id = $secondPlaceUserId;
            $lockedTournament->third_place_user_id = $thirdPlaceUserId;
            $lockedTournament->settlement_idempotency_key = $trimmedKey;
            $lockedTournament->settled_at = now();
            $lockedTournament->save();

            return [
                'tournament' => $lockedTournament->fresh(['registrations.user', 'organizer', 'firstPlace', 'secondPlace', 'thirdPlace']),
                'breakdown' => [
                    'total_pool_cents' => $totalPool,
                    'platform_rake_cents' => $treasuryRakeCents,
                    'organizer_rake_cents' => $organizerRakeCents,
                    'net_prize_pool_cents' => $netPoolCents,
                    'first_prize_cents' => $firstPrize,
                    'second_prize_cents' => $secondPrize,
                    'third_prize_cents' => $thirdPrize,
                    'prediction_pool_cents' => $predictionPoolCents,
                    'prediction_rake_cents' => $predRakeCents,
                ],
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Atomically cancel a tournament and refund 100% of escrowed entry fees back to registered players (0% fee).
     *
     * @return array{tournament: Tournament, refunded_count: int, total_refunded_cents: int}
     */
    public function cancelTournament(
        User $actor,
        Tournament $tournament,
        string $idempotencyKey,
        ?string $reason = null
    ): array {
        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire pour annuler le tournoi.');
        }

        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($actor, $tournament, $trimmedKey, $reason, $escrowWallet, $treasuryWallet): array {
            $lockedTournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            if ($lockedTournament->status === Tournament::STATUS_SETTLED) {
                throw new TournamentStateConflictException('Impossible d\'annuler un tournoi déjà clôturé et distribué.');
            }

            if ($lockedTournament->status === Tournament::STATUS_CANCELLED) {
                throw new TournamentStateConflictException('Ce tournoi est déjà annulé.');
            }

            $registrations = TournamentRegistration::where('tournament_id', $lockedTournament->id)
                ->where('status', TournamentRegistration::STATUS_REGISTERED)
                ->lockForUpdate()
                ->get();

            $lockedPredictions = TournamentPrediction::where('tournament_id', $lockedTournament->id)
                ->where('status', TournamentPrediction::STATUS_LOCKED)
                ->lockForUpdate()
                ->get();

            $participantUserIds = array_values(array_unique(array_merge(
                $registrations->pluck('user_id')->map(fn ($id): int => (int) $id)->all(),
                $lockedPredictions->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
            )));
            foreach ($participantUserIds as $uid) {
                Wallet::firstOrCreate(
                    ['user_id' => $uid],
                    [
                        'balance_cents' => 0,
                        'locked_escrow_cents' => 0,
                        'lifetime_won_cents' => 0,
                        'currency' => 'EUR',
                    ]
                );
            }

            $participantWallets = Wallet::whereIn('user_id', $participantUserIds)->get()->keyBy('user_id');

            $allWalletIds = [(int) $escrowWallet->id, (int) $treasuryWallet->id];
            foreach ($participantWallets as $pw) {
                $allWalletIds[] = (int) $pw->id;
            }
            $allWalletIds = array_values(array_unique($allWalletIds));
            sort($allWalletIds);

            $lockedWalletsById = Wallet::whereIn('id', $allWalletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWalletsById->get((int) $escrowWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWalletsById->get((int) $treasuryWallet->id);

            $totalRefundedCents = 0;
            $refundedCount = 0;

            foreach ($registrations as $registration) {
                $refundCents = (int) $registration->paid_entry_fee_cents;
                if ($refundCents <= 0) {
                    continue;
                }

                if ((int) $lockedEscrowWallet->balance_cents < $refundCents) {
                    throw new InsufficientFundsException('Fonds séquestrés insuffisants pour exécuter le remboursement.');
                }

                // Debit Escrow Vault
                $escrowBefore = (int) $lockedEscrowWallet->balance_cents;
                $escrowAfter = $escrowBefore - $refundCents;
                $lockedEscrowWallet->balance_cents = $escrowAfter;
                $lockedEscrowWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedEscrowWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_DEBIT,
                    'debit_cents' => $refundCents,
                    'credit_cents' => 0,
                    'balance_before_cents' => $escrowBefore,
                    'balance_after_cents' => $escrowAfter,
                    'idempotency_key' => "{$trimmedKey}:refund_debit:{$registration->id}",
                    'description' => "Remboursement séquestre suite à annulation — {$lockedTournament->title}",
                    'metadata' => [
                        'registration_id' => $registration->id,
                        'reason' => $reason ?? 'Annulation officielle du tournoi',
                    ],
                ]);

                // Credit Player Wallet
                $playerWalletId = (int) $participantWallets->get((int) $registration->user_id)->id;
                /** @var Wallet $lockedPlayerWallet */
                $lockedPlayerWallet = $lockedWalletsById->get($playerWalletId);

                $playerBefore = (int) $lockedPlayerWallet->balance_cents;
                $playerAfter = $playerBefore + $refundCents;
                $lockedPlayerWallet->balance_cents = $playerAfter;
                $lockedPlayerWallet->locked_escrow_cents = max(0, (int) $lockedPlayerWallet->locked_escrow_cents - $refundCents);
                $lockedPlayerWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedPlayerWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $refundCents,
                    'balance_before_cents' => $playerBefore,
                    'balance_after_cents' => $playerAfter,
                    'idempotency_key' => "{$trimmedKey}:refund_credit:{$registration->id}",
                    'description' => "Remboursement intégral Buy-in (100 %) — {$lockedTournament->title}",
                    'metadata' => [
                        'registration_id' => $registration->id,
                        'reason' => $reason ?? 'Annulation officielle du tournoi',
                    ],
                ]);

                $registration->status = TournamentRegistration::STATUS_REFUNDED;
                $registration->save();

                $totalRefundedCents += $refundCents;
                $refundedCount++;
            }

            // Refund 100% of any locked Spectator Predictions on tournament cancellation
            foreach ($lockedPredictions as $prediction) {
                $predStake = (int) $prediction->stake_cents;
                if ($predStake <= 0) {
                    continue;
                }

                if ((int) $lockedEscrowWallet->balance_cents < $predStake) {
                    throw new InsufficientFundsException('Fonds séquestrés insuffisants pour rembourser les pronostics.');
                }

                $eBefore = (int) $lockedEscrowWallet->balance_cents;
                $eAfter = $eBefore - $predStake;
                $lockedEscrowWallet->balance_cents = $eAfter;
                $lockedEscrowWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedEscrowWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_DEBIT,
                    'debit_cents' => $predStake,
                    'credit_cents' => 0,
                    'balance_before_cents' => $eBefore,
                    'balance_after_cents' => $eAfter,
                    'idempotency_key' => "{$trimmedKey}:pred_cancel_debit:{$prediction->id}",
                    'description' => "Libération séquestre Pronostic suite à annulation — {$lockedTournament->title}",
                ]);

                $pWalletId = (int) $participantWallets->get((int) $prediction->user_id)->id;
                /** @var Wallet $lockedPredWallet */
                $lockedPredWallet = $lockedWalletsById->get($pWalletId);

                $pBefore = (int) $lockedPredWallet->balance_cents;
                $pAfter = $pBefore + $predStake;
                $lockedPredWallet->balance_cents = $pAfter;
                $lockedPredWallet->locked_escrow_cents = max(0, (int) $lockedPredWallet->locked_escrow_cents - $predStake);
                $lockedPredWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedPredWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $predStake,
                    'balance_before_cents' => $pBefore,
                    'balance_after_cents' => $pAfter,
                    'idempotency_key' => "{$trimmedKey}:pred_cancel_credit:{$prediction->id}",
                    'description' => "Remboursement intégral Pronostic (100 %) — {$lockedTournament->title}",
                ]);

                $prediction->payout_cents = $predStake;
                $prediction->status = TournamentPrediction::STATUS_REFUNDED;
                $prediction->save();
            }

            // If tournament had extra sponsored pool remaining in escrow, release it back to Platform Treasury
            $remainingSponsoredInEscrow = max(0, (int) $lockedTournament->escrow_pool_cents - $totalRefundedCents);
            if ($remainingSponsoredInEscrow > 0 && (int) $lockedEscrowWallet->balance_cents >= $remainingSponsoredInEscrow) {
                $eBefore = (int) $lockedEscrowWallet->balance_cents;
                $eAfter = $eBefore - $remainingSponsoredInEscrow;
                $lockedEscrowWallet->balance_cents = $eAfter;
                $lockedEscrowWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedEscrowWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_DEBIT,
                    'debit_cents' => $remainingSponsoredInEscrow,
                    'credit_cents' => 0,
                    'balance_before_cents' => $eBefore,
                    'balance_after_cents' => $eAfter,
                    'idempotency_key' => "{$trimmedKey}:refund_sponsored_debit",
                    'description' => "Libération dotation sponsorisée suite à annulation — {$lockedTournament->title}",
                ]);

                $tBefore = (int) $lockedTreasuryWallet->balance_cents;
                $tAfter = $tBefore + $remainingSponsoredInEscrow;
                $lockedTreasuryWallet->balance_cents = $tAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $remainingSponsoredInEscrow,
                    'balance_before_cents' => $tBefore,
                    'balance_after_cents' => $tAfter,
                    'idempotency_key' => "{$trimmedKey}:refund_sponsored_credit",
                    'description' => "Retour dotation sponsorisée vers Trésorerie — {$lockedTournament->title}",
                ]);
            }

            $lockedTournament->escrow_pool_cents = 0;
            $lockedTournament->status = Tournament::STATUS_CANCELLED;
            $lockedTournament->save();

            return [
                'tournament' => $lockedTournament->fresh(['registrations.user', 'organizer']),
                'refunded_count' => $refundedCount,
                'total_refunded_cents' => $totalRefundedCents,
            ];
        });
    }

    /**
     * Ensure default Armory store items exist in the database.
     */
    public function ensureDefaultArmoryItems(): void
    {
        $items = [
            [
                'sku' => 'VKR-BADGE-SOVEREIGN',
                'name' => 'Insigne Tactique // ARCHONTE SOUVERAIN',
                'category' => ArmoryItem::CATEGORY_BADGE_TITLE,
                'description' => 'Titre d\'élite affiché dans le Cockpit, les Brackets et le Classement Général (+25 MMR ELO).',
                'price_cents' => 900, // 9,00 €
                'elo_bonus' => 25,
                'badge_label' => '★ ARCHONTE SOUVERAIN',
                'is_active' => true,
            ],
            [
                'sku' => 'VKR-SHIELD-ELO-PRO',
                'name' => 'Bouclier de Calibration // AEGIS MMR',
                'category' => ArmoryItem::CATEGORY_ELO_SHIELD,
                'description' => 'Licence tactique renforçant l\'indice de calibration compétitif de l\'opérateur (+40 MMR ELO).',
                'price_cents' => 1500, // 15,00 €
                'elo_bonus' => 40,
                'badge_label' => '◆ AEGIS CALIBRATED',
                'is_active' => true,
            ],
            [
                'sku' => 'VKR-FRAME-APEX-GOLD',
                'name' => 'Cadre d\'Identification // OBSIDIAN GOLD',
                'category' => ArmoryItem::CATEGORY_AVATAR_FRAME,
                'description' => 'Signature visuelle Or Souverain sur les fiches de duel 1v1 et les feuilles de match (+30 MMR ELO).',
                'price_cents' => 1200, // 12,00 €
                'elo_bonus' => 30,
                'badge_label' => '♛ OBSIDIAN GOLD',
                'is_active' => true,
            ],
            [
                'sku' => 'VKR-BADGE-IMMORTAL',
                'name' => 'Sceau de Commandement // VALKYRIE IMMORTAL',
                'category' => ArmoryItem::CATEGORY_BADGE_TITLE,
                'description' => 'Accréditation suprême du circuit compétitif VALKYR // ARENA (+75 MMR ELO).',
                'price_cents' => 2500, // 25,00 €
                'elo_bonus' => 75,
                'badge_label' => '⚡ VALKYRIE IMMORTAL',
                'is_active' => true,
            ],
        ];

        $existingSkus = ArmoryItem::whereIn('sku', array_column($items, 'sku'))
            ->pluck('sku')
            ->all();

        if (count($existingSkus) === count($items)) {
            return;
        }

        foreach ($items as $itemData) {
            if (! in_array($itemData['sku'], $existingSkus, true)) {
                ArmoryItem::firstOrCreate(
                    ['sku' => $itemData['sku']],
                    $itemData
                );
            }
        }
    }

    /**
     * Brick 1 (v4) — Create a 1v1 Cash-Match Wager Duel and lock challenger's stake in SYS_ESCROW_VAULT.
     *
     * @return array{duel: WagerDuel, wallet: Wallet, idempotent_replay: bool}
     */
    public function createWagerDuel(
        User $challenger,
        string $game,
        string $loadoutClass,
        int $stakeCents,
        string $idempotencyKey,
        ?string $customCode = null
    ): array {
        $this->assertWalletNotFrozen($challenger);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour créer un duel 1v1.');
        }

        if ($stakeCents < 500 || $stakeCents > 5_000_000) {
            throw new InvalidFinancialOperationException('La mise du duel 1v1 doit être comprise entre 5,00 € (500 centimes) et 50 000,00 €.');
        }

        $challengerWallet = $this->getOrCreateWallet($challenger);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];

        return DB::transaction(function () use (
            $challenger,
            $game,
            $loadoutClass,
            $stakeCents,
            $trimmedKey,
            $customCode,
            $challengerWallet,
            $escrowWallet
        ): array {
            $existing = WagerDuel::where('idempotency_key', $trimmedKey)->first();
            if ($existing !== null) {
                if ((int) $existing->challenger_id === (int) $challenger->id && (int) $existing->stake_cents === $stakeCents) {
                    return [
                        'duel' => $existing->load(['challenger', 'opponent', 'winner']),
                        'wallet' => Wallet::findOrFail($challengerWallet->id),
                        'idempotent_replay' => true,
                    ];
                }
                throw new IdempotencyConflictException('Clé d\'idempotence déjà utilisée pour un autre duel.');
            }

            $walletIds = array_values(array_unique([(int) $challengerWallet->id, (int) $escrowWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedChallengerWallet */
            $lockedChallengerWallet = $lockedWallets->get((int) $challengerWallet->id);
            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);

            if ((int) $lockedChallengerWallet->balance_cents < $stakeCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour couvrir la mise du duel 1v1 (%s).',
                        Wallet::formatCents((int) $lockedChallengerWallet->balance_cents),
                        Wallet::formatCents($stakeCents)
                    )
                );
            }

            $code = $customCode !== null && trim($customCode) !== ''
                ? strtoupper(trim($customCode))
                : ('DUEL-'.strtoupper(Str::random(5)));

            $cBefore = (int) $lockedChallengerWallet->balance_cents;
            $cAfter = $cBefore - $stakeCents;
            $lockedChallengerWallet->balance_cents = $cAfter;
            $lockedChallengerWallet->locked_escrow_cents = (int) $lockedChallengerWallet->locked_escrow_cents + $stakeCents;
            $lockedChallengerWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedChallengerWallet->id,
                'tournament_id' => null,
                'actor_id' => $challenger->id,
                'entry_type' => LedgerEntry::TYPE_DUEL_ESCROW_DEBIT,
                'debit_cents' => $stakeCents,
                'credit_cents' => 0,
                'balance_before_cents' => $cBefore,
                'balance_after_cents' => $cAfter,
                'idempotency_key' => "{$trimmedKey}:duel_challenger_debit",
                'description' => "Mise Duel 1v1 verrouillée en séquestre ({$code})",
                'metadata' => ['duel_code' => $code, 'game' => $game],
            ]);

            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore + $stakeCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => null,
                'actor_id' => $challenger->id,
                'entry_type' => LedgerEntry::TYPE_DUEL_ESCROW_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $stakeCents,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:duel_challenger_credit",
                'description' => "Séquestre Duel 1v1 reçu ({$challenger->gamertag} // {$code})",
                'metadata' => ['duel_code' => $code],
            ]);

            $duel = WagerDuel::create([
                'code' => $code,
                'challenger_id' => $challenger->id,
                'opponent_id' => null,
                'winner_id' => null,
                'game' => $game,
                'loadout_class' => in_array($loadoutClass, User::ALLOWED_CLASSES, true) ? $loadoutClass : 'VALKYRIE',
                'stake_cents' => $stakeCents,
                'escrow_pot_cents' => $stakeCents,
                'rake_bps' => WagerDuel::DEFAULT_RAKE_BPS,
                'platform_rake_cents' => 0,
                'winner_payout_cents' => 0,
                'status' => WagerDuel::STATUS_OPEN,
                'idempotency_key' => $trimmedKey,
            ]);

            return [
                'duel' => $duel->load(['challenger', 'opponent', 'winner']),
                'wallet' => $lockedChallengerWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 1 (v4) — Accept an OPEN 1v1 Wager Duel, locking the opponent's matching stake in SYS_ESCROW_VAULT.
     *
     * @return array{duel: WagerDuel, wallet: Wallet, idempotent_replay: bool}
     */
    public function acceptWagerDuel(User $opponent, WagerDuel $duel, string $idempotencyKey): array
    {
        $this->assertWalletNotFrozen($opponent);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour accepter un duel 1v1.');
        }

        $opponentWallet = $this->getOrCreateWallet($opponent);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];

        return DB::transaction(function () use ($opponent, $duel, $trimmedKey, $opponentWallet, $escrowWallet): array {
            /** @var WagerDuel $lockedDuel */
            $lockedDuel = WagerDuel::whereKey($duel->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedDuel->challenger_id === (int) $opponent->id) {
                throw new InvalidFinancialOperationException('Vous ne pouvez pas affronter votre propre défi 1v1.');
            }

            $existingEntry = LedgerEntry::where('idempotency_key', "{$trimmedKey}:duel_opponent_debit")->first();
            if ($existingEntry !== null && (int) $lockedDuel->opponent_id === (int) $opponent->id) {
                return [
                    'duel' => $lockedDuel->load(['challenger', 'opponent', 'winner']),
                    'wallet' => Wallet::findOrFail($opponentWallet->id),
                    'idempotent_replay' => true,
                ];
            }

            if ($lockedDuel->status !== WagerDuel::STATUS_OPEN) {
                throw new TournamentStateConflictException('Ce duel 1v1 n\'est plus ouvert aux adversaires.');
            }

            $stakeCents = (int) $lockedDuel->stake_cents;

            $walletIds = array_values(array_unique([(int) $opponentWallet->id, (int) $escrowWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedOpponentWallet */
            $lockedOpponentWallet = $lockedWallets->get((int) $opponentWallet->id);
            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);

            if ((int) $lockedOpponentWallet->balance_cents < $stakeCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour accepter la mise du duel (%s).',
                        Wallet::formatCents((int) $lockedOpponentWallet->balance_cents),
                        Wallet::formatCents($stakeCents)
                    )
                );
            }

            $oBefore = (int) $lockedOpponentWallet->balance_cents;
            $oAfter = $oBefore - $stakeCents;
            $lockedOpponentWallet->balance_cents = $oAfter;
            $lockedOpponentWallet->locked_escrow_cents = (int) $lockedOpponentWallet->locked_escrow_cents + $stakeCents;
            $lockedOpponentWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedOpponentWallet->id,
                'tournament_id' => null,
                'actor_id' => $opponent->id,
                'entry_type' => LedgerEntry::TYPE_DUEL_ESCROW_DEBIT,
                'debit_cents' => $stakeCents,
                'credit_cents' => 0,
                'balance_before_cents' => $oBefore,
                'balance_after_cents' => $oAfter,
                'idempotency_key' => "{$trimmedKey}:duel_opponent_debit",
                'description' => "Acceptation Duel 1v1 — Mise verrouillée ({$lockedDuel->code})",
                'metadata' => ['duel_code' => $lockedDuel->code],
            ]);

            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore + $stakeCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => null,
                'actor_id' => $opponent->id,
                'entry_type' => LedgerEntry::TYPE_DUEL_ESCROW_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $stakeCents,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:duel_opponent_credit",
                'description' => "Séquestre Duel 1v1 reçu ({$opponent->gamertag} // {$lockedDuel->code})",
                'metadata' => ['duel_code' => $lockedDuel->code],
            ]);

            $lockedDuel->opponent_id = $opponent->id;
            $lockedDuel->escrow_pot_cents = (int) $lockedDuel->escrow_pot_cents + $stakeCents;
            $lockedDuel->status = WagerDuel::STATUS_IN_PROGRESS;
            $lockedDuel->save();

            return [
                'duel' => $lockedDuel->fresh(['challenger', 'opponent', 'winner']),
                'wallet' => $lockedOpponentWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 1 (v4) — Settle an IN_PROGRESS 1v1 Wager Duel: 8% Rake to SYS_TREASURY + 92% Payout to Winner.
     *
     * @return array{duel: WagerDuel, idempotent_replay: bool}
     */
    public function settleWagerDuel(User $actor, WagerDuel $duel, int $winnerUserId, string $idempotencyKey): array
    {
        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour clôturer le duel.');
        }

        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($actor, $duel, $winnerUserId, $trimmedKey, $escrowWallet, $treasuryWallet): array {
            /** @var WagerDuel $lockedDuel */
            $lockedDuel = WagerDuel::whereKey($duel->id)->lockForUpdate()->firstOrFail();

            $isParticipant = (int) $actor->id === (int) $lockedDuel->challenger_id
                || (int) $actor->id === (int) $lockedDuel->opponent_id;
            if (! $actor->isAdmin() && ! $actor->isOrganizer() && ! $isParticipant) {
                throw new InvalidFinancialOperationException('Vous n\'êtes pas autorisé à clôturer ce duel 1v1.');
            }

            if ($lockedDuel->status === WagerDuel::STATUS_SETTLED) {
                if ((int) $lockedDuel->winner_id === $winnerUserId) {
                    return [
                        'duel' => $lockedDuel->load(['challenger', 'opponent', 'winner']),
                        'idempotent_replay' => true,
                    ];
                }
                throw new TournamentStateConflictException('Ce duel 1v1 a déjà été clôturé.');
            }

            if ($lockedDuel->status !== WagerDuel::STATUS_IN_PROGRESS || $lockedDuel->opponent_id === null) {
                throw new TournamentStateConflictException('Un duel 1v1 doit avoir un adversaire engagé (IN_PROGRESS) avant d\'être clôturé.');
            }

            if ($winnerUserId !== (int) $lockedDuel->challenger_id && $winnerUserId !== (int) $lockedDuel->opponent_id) {
                throw new InvalidFinancialOperationException('Le vainqueur désigné doit être l\'un des deux duellistes.');
            }

            $challengerUser = User::findOrFail($lockedDuel->challenger_id);
            $opponentUser = User::findOrFail($lockedDuel->opponent_id);
            $challengerWallet = $this->getOrCreateWallet($challengerUser);
            $opponentWallet = $this->getOrCreateWallet($opponentUser);

            $walletIds = array_values(array_unique([
                (int) $escrowWallet->id,
                (int) $treasuryWallet->id,
                (int) $challengerWallet->id,
                (int) $opponentWallet->id,
            ]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);
            /** @var Wallet $lockedChallengerWallet */
            $lockedChallengerWallet = $lockedWallets->get((int) $challengerWallet->id);
            /** @var Wallet $lockedOpponentWallet */
            $lockedOpponentWallet = $lockedWallets->get((int) $opponentWallet->id);

            $potCents = (int) $lockedDuel->escrow_pot_cents;
            $stakeCents = (int) $lockedDuel->stake_cents;
            $rakeBps = (int) ($lockedDuel->rake_bps ?: WagerDuel::DEFAULT_RAKE_BPS);
            $rakeCents = intdiv($potCents * $rakeBps, 10000);
            $payoutCents = $potCents - $rakeCents;

            // 1. Release pot from Escrow Vault
            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore - $potCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => null,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_ESCROW_RELEASE_DEBIT,
                'debit_cents' => $potCents,
                'credit_cents' => 0,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:duel_escrow_release",
                'description' => "Libération séquestre Duel 1v1 ({$lockedDuel->code})",
            ]);

            // 2. Credit 8% Rake to Platform Treasury
            if ($rakeCents > 0) {
                $tBefore = (int) $lockedTreasuryWallet->balance_cents;
                $tAfter = $tBefore + $rakeCents;
                $lockedTreasuryWallet->balance_cents = $tAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => null,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_PLATFORM_RAKE_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $rakeCents,
                    'balance_before_cents' => $tBefore,
                    'balance_after_cents' => $tAfter,
                    'idempotency_key' => "{$trimmedKey}:duel_platform_rake",
                    'description' => "Commission Duel 1v1 (8 %) — {$lockedDuel->code}",
                ]);
            }

            // 3. Credit 92% Payout to Winner Wallet
            $winnerWallet = $winnerUserId === (int) $lockedDuel->challenger_id
                ? $lockedChallengerWallet
                : $lockedOpponentWallet;

            $wBefore = (int) $winnerWallet->balance_cents;
            $wAfter = $wBefore + $payoutCents;
            $winnerWallet->balance_cents = $wAfter;
            $winnerWallet->lifetime_won_cents = (int) $winnerWallet->lifetime_won_cents + $payoutCents;
            $winnerWallet->save();

            LedgerEntry::create([
                'wallet_id' => $winnerWallet->id,
                'tournament_id' => null,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntry::TYPE_DUEL_PAYOUT_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $payoutCents,
                'balance_before_cents' => $wBefore,
                'balance_after_cents' => $wAfter,
                'idempotency_key' => "{$trimmedKey}:duel_winner_payout",
                'description' => "Victoire Duel 1v1 (92 % Cash-Match) — {$lockedDuel->code}",
            ]);

            // 4. Release locked_escrow_cents on both duelists
            $lockedChallengerWallet->locked_escrow_cents = max(0, (int) $lockedChallengerWallet->locked_escrow_cents - $stakeCents);
            $lockedChallengerWallet->save();

            $lockedOpponentWallet->locked_escrow_cents = max(0, (int) $lockedOpponentWallet->locked_escrow_cents - $stakeCents);
            $lockedOpponentWallet->save();

            User::whereKey($winnerUserId)->increment('elo_rating', 15);

            $lockedDuel->winner_id = $winnerUserId;
            $lockedDuel->platform_rake_cents = $rakeCents;
            $lockedDuel->winner_payout_cents = $payoutCents;
            $lockedDuel->status = WagerDuel::STATUS_SETTLED;
            $lockedDuel->settled_at = now();
            $lockedDuel->save();

            return [
                'duel' => $lockedDuel->fresh(['challenger', 'opponent', 'winner']),
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 2 (v4) — Purchase an Armory Item from the Sovereign Store (100% revenue to SYS_TREASURY).
     *
     * @return array{purchase: UserArmoryPurchase, user: User, wallet: Wallet, idempotent_replay: bool}
     */
    public function purchaseArmoryItem(User $buyer, ArmoryItem $item, string $idempotencyKey): array
    {
        $this->assertWalletNotFrozen($buyer);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour l\'achat Armory.');
        }

        if (! $item->is_active) {
            throw new InvalidFinancialOperationException('Cet article de l\'Armurerie n\'est plus disponible.');
        }

        $priceCents = (int) $item->price_cents;
        $buyerWallet = $this->getOrCreateWallet($buyer);
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($buyer, $item, $priceCents, $trimmedKey, $buyerWallet, $treasuryWallet): array {
            $existing = UserArmoryPurchase::where('idempotency_key', $trimmedKey)->first();
            if ($existing !== null && (int) $existing->user_id === (int) $buyer->id && (int) $existing->armory_item_id === (int) $item->id) {
                return [
                    'purchase' => $existing->load('armoryItem'),
                    'user' => $buyer->fresh(),
                    'wallet' => Wallet::findOrFail($buyerWallet->id),
                    'idempotent_replay' => true,
                ];
            }

            $walletIds = array_values(array_unique([(int) $buyerWallet->id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedBuyerWallet */
            $lockedBuyerWallet = $lockedWallets->get((int) $buyerWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            $alreadyOwned = UserArmoryPurchase::where('user_id', $buyer->id)
                ->where('armory_item_id', $item->id)
                ->exists();
            if ($alreadyOwned) {
                throw new InvalidFinancialOperationException("Vous possédez déjà l'article « {$item->name} » dans votre inventaire Armory.");
            }

            if ((int) $lockedBuyerWallet->balance_cents < $priceCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour acquérir « %s » (%s).',
                        Wallet::formatCents((int) $lockedBuyerWallet->balance_cents),
                        $item->name,
                        Wallet::formatCents($priceCents)
                    )
                );
            }

            $bBefore = (int) $lockedBuyerWallet->balance_cents;
            $bAfter = $bBefore - $priceCents;
            $lockedBuyerWallet->balance_cents = $bAfter;
            $lockedBuyerWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedBuyerWallet->id,
                'tournament_id' => null,
                'actor_id' => $buyer->id,
                'entry_type' => LedgerEntry::TYPE_ARMORY_PURCHASE_DEBIT,
                'debit_cents' => $priceCents,
                'credit_cents' => 0,
                'balance_before_cents' => $bBefore,
                'balance_after_cents' => $bAfter,
                'idempotency_key' => "{$trimmedKey}:armory_debit",
                'description' => "Acquisition Boutique Armory — {$item->name}",
                'metadata' => ['sku' => $item->sku, 'badge_label' => $item->badge_label],
            ]);

            if ((int) $lockedTreasuryWallet->id === (int) $lockedBuyerWallet->id) {
                $lockedTreasuryWallet = $lockedBuyerWallet;
            }

            $tBefore = (int) $lockedTreasuryWallet->balance_cents;
            $tAfter = $tBefore + $priceCents;
            $lockedTreasuryWallet->balance_cents = $tAfter;
            $lockedTreasuryWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedTreasuryWallet->id,
                'tournament_id' => null,
                'actor_id' => $buyer->id,
                'entry_type' => LedgerEntry::TYPE_ARMORY_REVENUE_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $priceCents,
                'balance_before_cents' => $tBefore,
                'balance_after_cents' => $tAfter,
                'idempotency_key' => "{$trimmedKey}:armory_credit",
                'description' => "Revenu Boutique Armory ({$buyer->gamertag}) — {$item->name}",
                'metadata' => ['sku' => $item->sku],
            ]);

            $purchase = UserArmoryPurchase::create([
                'user_id' => $buyer->id,
                'armory_item_id' => $item->id,
                'paid_cents' => $priceCents,
                'idempotency_key' => $trimmedKey,
            ]);

            $buyer->equipped_badge = $item->badge_label;
            $buyer->elo_rating = (int) $buyer->elo_rating + (int) $item->elo_bonus;
            $buyer->save();

            return [
                'purchase' => $purchase->load('armoryItem'),
                'user' => $buyer->fresh(),
                'wallet' => $lockedBuyerWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 3 (v4) — Place a Spectator Prediction stake on a tournament participant (locked in SYS_ESCROW_VAULT).
     *
     * @return array{prediction: TournamentPrediction, wallet: Wallet, idempotent_replay: bool}
     */
    public function placeTournamentPrediction(
        User $user,
        Tournament $tournament,
        int $predictedWinnerUserId,
        int $stakeCents,
        string $idempotencyKey
    ): array {
        $this->assertWalletNotFrozen($user);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour engager un pronostic.');
        }

        if ($stakeCents < 200 || $stakeCents > 5_000_000) {
            throw new InvalidFinancialOperationException('La mise du pronostic doit être comprise entre 2,00 € (200 centimes) et 50 000,00 €.');
        }

        $userWallet = $this->getOrCreateWallet($user);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];

        return DB::transaction(function () use (
            $user,
            $tournament,
            $predictedWinnerUserId,
            $stakeCents,
            $trimmedKey,
            $userWallet,
            $escrowWallet
        ): array {
            $existing = TournamentPrediction::where('idempotency_key', $trimmedKey)->first();
            if ($existing !== null) {
                return [
                    'prediction' => $existing->load(['user', 'predictedWinner']),
                    'wallet' => Wallet::findOrFail($userWallet->id),
                    'idempotent_replay' => true,
                ];
            }

            $lockedTournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedTournament->status, [Tournament::STATUS_SETTLED, Tournament::STATUS_CANCELLED], true)) {
                throw new TournamentStateConflictException('Les pronostics sont fermés sur ce tournoi.');
            }

            $isRegistered = TournamentRegistration::where('tournament_id', $lockedTournament->id)
                ->where('user_id', $predictedWinnerUserId)
                ->exists();
            if (! $isRegistered) {
                throw new InvalidFinancialOperationException('Le joueur sélectionné pour le pronostic n\'est pas inscrit à cette arène.');
            }

            $walletIds = array_values(array_unique([(int) $userWallet->id, (int) $escrowWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedUserWallet */
            $lockedUserWallet = $lockedWallets->get((int) $userWallet->id);
            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);

            if ((int) $lockedUserWallet->balance_cents < $stakeCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour engager le pronostic de %s.',
                        Wallet::formatCents((int) $lockedUserWallet->balance_cents),
                        Wallet::formatCents($stakeCents)
                    )
                );
            }

            $uBefore = (int) $lockedUserWallet->balance_cents;
            $uAfter = $uBefore - $stakeCents;
            $lockedUserWallet->balance_cents = $uAfter;
            $lockedUserWallet->locked_escrow_cents = (int) $lockedUserWallet->locked_escrow_cents + $stakeCents;
            $lockedUserWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedUserWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_PREDICTION_STAKE_DEBIT,
                'debit_cents' => $stakeCents,
                'credit_cents' => 0,
                'balance_before_cents' => $uBefore,
                'balance_after_cents' => $uAfter,
                'idempotency_key' => "{$trimmedKey}:pred_debit",
                'description' => "Pronostic Spectateur verrouillé — {$lockedTournament->title}",
                'metadata' => ['predicted_winner_user_id' => $predictedWinnerUserId],
            ]);

            if ((int) $lockedEscrowWallet->id === (int) $lockedUserWallet->id) {
                $lockedEscrowWallet = $lockedUserWallet;
            }

            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore + $stakeCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => $lockedTournament->id,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_PREDICTION_STAKE_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $stakeCents,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:pred_credit",
                'description' => "Séquestre Pronostic reçu ({$user->gamertag}) — {$lockedTournament->title}",
                'metadata' => ['predicted_winner_user_id' => $predictedWinnerUserId],
            ]);

            $prediction = TournamentPrediction::create([
                'tournament_id' => $lockedTournament->id,
                'user_id' => $user->id,
                'predicted_winner_user_id' => $predictedWinnerUserId,
                'stake_cents' => $stakeCents,
                'payout_cents' => 0,
                'status' => TournamentPrediction::STATUS_LOCKED,
                'idempotency_key' => $trimmedKey,
            ]);

            return [
                'prediction' => $prediction->load(['user', 'predictedWinner']),
                'wallet' => $lockedUserWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 4 (v4) — Create a B2B Clan Franchise (29,00 € / 2900 cents paid from Founder Wallet to SYS_TREASURY).
     *
     * @return array{clan: Clan, user: User, wallet: Wallet, idempotent_replay: bool}
     */
    public function createClan(
        User $founder,
        string $tag,
        string $name,
        string $idempotencyKey,
        int $revenueShareBps = 1500
    ): array {
        $this->assertWalletNotFrozen($founder);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('Clé d\'idempotence obligatoire pour fonder un Clan.');
        }

        $cleanTag = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($tag)) ?: '');
        if (strlen($cleanTag) < 2 || strlen($cleanTag) > 6) {
            throw new InvalidFinancialOperationException('Le tag du Clan doit comporter entre 2 et 6 caractères alphanumériques.');
        }

        $priceCents = self::CLAN_LICENSE_PRICE_CENTS;
        $founderWallet = $this->getOrCreateWallet($founder);
        $systemWallets = $this->ensureSystemWallets();
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($founder, $cleanTag, $name, $revenueShareBps, $priceCents, $trimmedKey, $founderWallet, $treasuryWallet): array {
            $existingEntry = LedgerEntry::where('idempotency_key', "{$trimmedKey}:clan_debit")->first();
            if ($existingEntry !== null) {
                $existingClan = Clan::where('owner_id', $founder->id)->latest('id')->firstOrFail();

                return [
                    'clan' => $existingClan->load('owner'),
                    'user' => $founder->fresh(),
                    'wallet' => Wallet::findOrFail($founderWallet->id),
                    'idempotent_replay' => true,
                ];
            }

            if (Clan::where('tag', $cleanTag)->exists()) {
                throw new TournamentStateConflictException("Le tag de Clan [{$cleanTag}] est déjà enregistré.");
            }

            $walletIds = array_values(array_unique([(int) $founderWallet->id, (int) $treasuryWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedFounderWallet */
            $lockedFounderWallet = $lockedWallets->get((int) $founderWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);

            if ((int) $lockedFounderWallet->balance_cents < $priceCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour acquérir la Licence de Franchise Clan (%s).',
                        Wallet::formatCents((int) $lockedFounderWallet->balance_cents),
                        Wallet::formatCents($priceCents)
                    )
                );
            }

            $fBefore = (int) $lockedFounderWallet->balance_cents;
            $fAfter = $fBefore - $priceCents;
            $lockedFounderWallet->balance_cents = $fAfter;
            $lockedFounderWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedFounderWallet->id,
                'tournament_id' => null,
                'actor_id' => $founder->id,
                'entry_type' => LedgerEntry::TYPE_CLAN_LICENSE_DEBIT,
                'debit_cents' => $priceCents,
                'credit_cents' => 0,
                'balance_before_cents' => $fBefore,
                'balance_after_cents' => $fAfter,
                'idempotency_key' => "{$trimmedKey}:clan_debit",
                'description' => "Licence Franchise Clan [{$cleanTag}] {$name}",
                'metadata' => ['clan_tag' => $cleanTag],
            ]);

            if ((int) $lockedTreasuryWallet->id === (int) $lockedFounderWallet->id) {
                $lockedTreasuryWallet = $lockedFounderWallet;
            }

            $tBefore = (int) $lockedTreasuryWallet->balance_cents;
            $tAfter = $tBefore + $priceCents;
            $lockedTreasuryWallet->balance_cents = $tAfter;
            $lockedTreasuryWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedTreasuryWallet->id,
                'tournament_id' => null,
                'actor_id' => $founder->id,
                'entry_type' => LedgerEntry::TYPE_CLAN_LICENSE_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $priceCents,
                'balance_before_cents' => $tBefore,
                'balance_after_cents' => $tAfter,
                'idempotency_key' => "{$trimmedKey}:clan_credit",
                'description' => "Revenu Licence Franchise Clan [{$cleanTag}] ({$founder->gamertag})",
                'metadata' => ['clan_tag' => $cleanTag],
            ]);

            $clan = Clan::create([
                'tag' => $cleanTag,
                'name' => trim($name),
                'owner_id' => $founder->id,
                'revenue_share_bps' => max(0, min(5000, $revenueShareBps)),
                'member_count' => 1,
                'license_paid_cents' => $priceCents,
            ]);

            $founder->clan_tag = $cleanTag;
            $founder->save();

            return [
                'clan' => $clan->load('owner'),
                'user' => $founder->fresh(),
                'wallet' => $lockedFounderWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 4 (v4) — Join an existing Clan Franchise.
     */
    public function joinClan(User $user, Clan $clan): Clan
    {
        return DB::transaction(function () use ($user, $clan): Clan {
            /** @var Clan $lockedClan */
            $lockedClan = Clan::whereKey($clan->id)->lockForUpdate()->firstOrFail();

            $previousTag = $user->getAttributes()['clan_tag'] ?? null;
            $user->clan_tag = $lockedClan->tag;
            $user->save();

            if ($previousTag !== null && $previousTag !== $lockedClan->tag) {
                Clan::where('tag', $previousTag)->update([
                    'member_count' => User::where('clan_tag', $previousTag)->count(),
                ]);
            }

            $lockedClan->member_count = User::where('clan_tag', $lockedClan->tag)->count();
            $lockedClan->save();

            return $lockedClan->fresh('owner');
        });
    }

    /**
     * Brick 5 (v4) — Redeem an Admin-issued Prepaid Voucher Code into the operator's Wallet.
     *
     * @return array{redemption: VoucherRedemption, voucher: VoucherCode, wallet: Wallet, ledger_entry: LedgerEntry|null, idempotent_replay: bool}
     */
    public function redeemVoucher(User $user, string $code, string $idempotencyKey): array
    {
        $this->assertWalletNotFrozen($user);

        $trimmedKey = trim($idempotencyKey);
        $cleanCode = strtoupper(trim($code));
        if ($trimmedKey === '' || $cleanCode === '') {
            throw new InvalidFinancialOperationException('Le code Voucher et la clé d\'idempotence sont obligatoires.');
        }

        $userWallet = $this->getOrCreateWallet($user);

        return DB::transaction(function () use ($user, $cleanCode, $trimmedKey, $userWallet): array {
            $existingByKey = VoucherRedemption::where('idempotency_key', $trimmedKey)->first();
            if ($existingByKey !== null && (int) $existingByKey->user_id === (int) $user->id) {
                $existingEntry = LedgerEntry::where('idempotency_key', "{$trimmedKey}:voucher_deposit")->first();

                return [
                    'redemption' => $existingByKey,
                    'voucher' => VoucherCode::findOrFail($existingByKey->voucher_code_id),
                    'wallet' => Wallet::findOrFail($userWallet->id),
                    'ledger_entry' => $existingEntry,
                    'idempotent_replay' => true,
                ];
            }

            /** @var VoucherCode|null $lockedVoucher */
            $lockedVoucher = VoucherCode::where('code', $cleanCode)->lockForUpdate()->first();
            if ($lockedVoucher === null || ! $lockedVoucher->is_active) {
                throw new InvalidFinancialOperationException('Ce code Voucher est invalide ou désactivé.');
            }

            if ((int) $lockedVoucher->used_count >= (int) $lockedVoucher->max_uses) {
                throw new InvalidFinancialOperationException('Ce code Voucher a atteint son quota maximal d\'utilisations.');
            }

            $alreadyRedeemed = VoucherRedemption::where('voucher_code_id', $lockedVoucher->id)
                ->where('user_id', $user->id)
                ->exists();
            if ($alreadyRedeemed) {
                throw new InvalidFinancialOperationException('Vous avez déjà activé ce code Voucher sur votre Wallet.');
            }

            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::whereKey($userWallet->id)->lockForUpdate()->firstOrFail();

            $creditCents = (int) $lockedVoucher->credit_amount_cents;
            $before = (int) $lockedWallet->balance_cents;
            $after = $before + $creditCents;
            $lockedWallet->balance_cents = $after;
            $lockedWallet->save();

            $entry = LedgerEntry::create([
                'wallet_id' => $lockedWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_DEPOSIT,
                'debit_cents' => 0,
                'credit_cents' => $creditCents,
                'balance_before_cents' => $before,
                'balance_after_cents' => $after,
                'idempotency_key' => "{$trimmedKey}:voucher_deposit",
                'description' => "Activation Code Voucher Prépayé « {$lockedVoucher->code} »",
                'metadata' => ['voucher_code' => $lockedVoucher->code],
            ]);

            $lockedVoucher->used_count = (int) $lockedVoucher->used_count + 1;
            if ((int) $lockedVoucher->used_count >= (int) $lockedVoucher->max_uses) {
                $lockedVoucher->is_active = false;
            }
            $lockedVoucher->save();

            $redemption = VoucherRedemption::create([
                'voucher_code_id' => $lockedVoucher->id,
                'user_id' => $user->id,
                'credited_cents' => $creditCents,
                'idempotency_key' => $trimmedKey,
            ]);

            return [
                'redemption' => $redemption,
                'voucher' => $lockedVoucher,
                'wallet' => $lockedWallet,
                'ledger_entry' => $entry,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 6 (v4) — Open a Tournament Dispute by locking a 5,00 € (500 cents) Arbitration Bond in SYS_ESCROW_VAULT.
     *
     * @return array{dispute: TournamentDispute, wallet: Wallet, idempotent_replay: bool}
     */
    public function openTournamentDispute(
        User $challenger,
        Tournament $tournament,
        string $reason,
        ?string $evidenceUrl,
        string $idempotencyKey
    ): array {
        $this->assertWalletNotFrozen($challenger);

        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '' || trim($reason) === '') {
            throw new InvalidFinancialOperationException('Le motif du litige et la clé d\'idempotence sont obligatoires.');
        }

        $bondCents = self::DISPUTE_BOND_CENTS;
        $challengerWallet = $this->getOrCreateWallet($challenger);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];

        return DB::transaction(function () use (
            $challenger,
            $tournament,
            $reason,
            $evidenceUrl,
            $bondCents,
            $trimmedKey,
            $challengerWallet,
            $escrowWallet
        ): array {
            $existing = TournamentDispute::where('idempotency_key', $trimmedKey)->first();
            if ($existing !== null) {
                return [
                    'dispute' => $existing->load(['tournament', 'challenger']),
                    'wallet' => Wallet::findOrFail($challengerWallet->id),
                    'idempotent_replay' => true,
                ];
            }

            $walletIds = array_values(array_unique([(int) $challengerWallet->id, (int) $escrowWallet->id]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedChallengerWallet */
            $lockedChallengerWallet = $lockedWallets->get((int) $challengerWallet->id);
            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);

            if ((int) $lockedChallengerWallet->balance_cents < $bondCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde Wallet insuffisant (%s) pour consigner la caution d\'arbitrage de %s.',
                        Wallet::formatCents((int) $lockedChallengerWallet->balance_cents),
                        Wallet::formatCents($bondCents)
                    )
                );
            }

            $cBefore = (int) $lockedChallengerWallet->balance_cents;
            $cAfter = $cBefore - $bondCents;
            $lockedChallengerWallet->balance_cents = $cAfter;
            $lockedChallengerWallet->locked_escrow_cents = (int) $lockedChallengerWallet->locked_escrow_cents + $bondCents;
            $lockedChallengerWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedChallengerWallet->id,
                'tournament_id' => $tournament->id,
                'actor_id' => $challenger->id,
                'entry_type' => LedgerEntry::TYPE_DISPUTE_BOND_DEBIT,
                'debit_cents' => $bondCents,
                'credit_cents' => 0,
                'balance_before_cents' => $cBefore,
                'balance_after_cents' => $cAfter,
                'idempotency_key' => "{$trimmedKey}:dispute_bond_debit",
                'description' => "Caution d'Arbitrage Litige verrouillée — {$tournament->title}",
            ]);

            if ((int) $lockedEscrowWallet->id === (int) $lockedChallengerWallet->id) {
                $lockedEscrowWallet = $lockedChallengerWallet;
            }

            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore + $bondCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => $tournament->id,
                'actor_id' => $challenger->id,
                'entry_type' => LedgerEntry::TYPE_DISPUTE_BOND_CREDIT,
                'debit_cents' => 0,
                'credit_cents' => $bondCents,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:dispute_bond_credit",
                'description' => "Caution Litige reçue ({$challenger->gamertag}) — {$tournament->title}",
            ]);

            $dispute = TournamentDispute::create([
                'tournament_id' => $tournament->id,
                'challenger_user_id' => $challenger->id,
                'bond_cents' => $bondCents,
                'reason' => trim($reason),
                'evidence_url' => $evidenceUrl !== null && trim($evidenceUrl) !== '' ? trim($evidenceUrl) : null,
                'status' => TournamentDispute::STATUS_OPEN,
                'idempotency_key' => $trimmedKey,
            ]);

            return [
                'dispute' => $dispute->load(['tournament', 'challenger']),
                'wallet' => $lockedChallengerWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Brick 6 (v4) — Resolve an OPEN Tournament Dispute in Admin Back-Office:
     * - UPHELD: refund 500 cents bond from SYS_ESCROW_VAULT back to Challenger Wallet.
     * - REJECTED: forfeit 500 cents bond from SYS_ESCROW_VAULT to SYS_TREASURY (DISPUTE_FORFEIT_CREDIT).
     *
     * @return array{dispute: TournamentDispute, wallet: Wallet, idempotent_replay: bool}
     */
    public function resolveTournamentDispute(
        User $admin,
        TournamentDispute $dispute,
        string $decision,
        ?string $notes = null,
        string $idempotencyKey = ''
    ): array {
        $cleanDecision = strtoupper(trim($decision));
        if (! in_array($cleanDecision, ['UPHELD', 'REJECTED'], true)) {
            throw new InvalidFinancialOperationException('La décision d\'arbitrage doit être UPHELD ou REJECTED.');
        }

        $trimmedKey = trim($idempotencyKey) ?: ('dispute:resolve:'.$dispute->id);
        $systemWallets = $this->ensureSystemWallets();
        $escrowWallet = $systemWallets['escrow'];
        $treasuryWallet = $systemWallets['treasury'];

        return DB::transaction(function () use ($admin, $dispute, $cleanDecision, $notes, $trimmedKey, $escrowWallet, $treasuryWallet): array {
            /** @var TournamentDispute $lockedDispute */
            $lockedDispute = TournamentDispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();

            if ($lockedDispute->status !== TournamentDispute::STATUS_OPEN) {
                throw new TournamentStateConflictException('Ce litige a déjà été tranché par le Tribunal d\'Arbitrage.');
            }

            $challenger = User::findOrFail($lockedDispute->challenger_user_id);
            $challengerWallet = $this->getOrCreateWallet($challenger);

            $walletIds = array_values(array_unique([
                (int) $escrowWallet->id,
                (int) $treasuryWallet->id,
                (int) $challengerWallet->id,
            ]));
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $lockedEscrowWallet */
            $lockedEscrowWallet = $lockedWallets->get((int) $escrowWallet->id);
            /** @var Wallet $lockedTreasuryWallet */
            $lockedTreasuryWallet = $lockedWallets->get((int) $treasuryWallet->id);
            /** @var Wallet $lockedChallengerWallet */
            $lockedChallengerWallet = $lockedWallets->get((int) $challengerWallet->id);

            $bondCents = (int) $lockedDispute->bond_cents;

            // 1. Release bond from Escrow Vault
            $eBefore = (int) $lockedEscrowWallet->balance_cents;
            $eAfter = $eBefore - $bondCents;
            $lockedEscrowWallet->balance_cents = $eAfter;
            $lockedEscrowWallet->save();

            LedgerEntry::create([
                'wallet_id' => $lockedEscrowWallet->id,
                'tournament_id' => $lockedDispute->tournament_id,
                'actor_id' => $admin->id,
                'entry_type' => LedgerEntry::TYPE_ESCROW_RELEASE_DEBIT,
                'debit_cents' => $bondCents,
                'credit_cents' => 0,
                'balance_before_cents' => $eBefore,
                'balance_after_cents' => $eAfter,
                'idempotency_key' => "{$trimmedKey}:dispute_escrow_release",
                'description' => "Libération Caution d'Arbitrage Litige #{$lockedDispute->id}",
            ]);

            $lockedChallengerWallet->locked_escrow_cents = max(0, (int) $lockedChallengerWallet->locked_escrow_cents - $bondCents);
            $lockedChallengerWallet->save();

            if ($cleanDecision === 'UPHELD') {
                // Refund bond to Challenger Wallet
                $cBefore = (int) $lockedChallengerWallet->balance_cents;
                $cAfter = $cBefore + $bondCents;
                $lockedChallengerWallet->balance_cents = $cAfter;
                $lockedChallengerWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedChallengerWallet->id,
                    'tournament_id' => $lockedDispute->tournament_id,
                    'actor_id' => $admin->id,
                    'entry_type' => LedgerEntry::TYPE_TOURNAMENT_REFUND_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $bondCents,
                    'balance_before_cents' => $cBefore,
                    'balance_after_cents' => $cAfter,
                    'idempotency_key' => "{$trimmedKey}:dispute_upheld_refund",
                    'description' => "Remboursement Caution d'Arbitrage (Litige #{$lockedDispute->id} fondé)",
                ]);

                $lockedDispute->status = TournamentDispute::STATUS_UPHELD_REFUNDED;
            } else {
                // Forfeit bond to Platform Treasury
                $tBefore = (int) $lockedTreasuryWallet->balance_cents;
                $tAfter = $tBefore + $bondCents;
                $lockedTreasuryWallet->balance_cents = $tAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => $lockedDispute->tournament_id,
                    'actor_id' => $admin->id,
                    'entry_type' => LedgerEntry::TYPE_DISPUTE_FORFEIT_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $bondCents,
                    'balance_before_cents' => $tBefore,
                    'balance_after_cents' => $tAfter,
                    'idempotency_key' => "{$trimmedKey}:dispute_forfeit_treasury",
                    'description' => "Confiscation Caution d'Arbitrage Litige abusif #{$lockedDispute->id} ({$challenger->gamertag})",
                ]);

                $lockedDispute->status = TournamentDispute::STATUS_REJECTED_FORFEITED;
            }

            $lockedDispute->resolved_by_admin_id = $admin->id;
            $lockedDispute->admin_resolution_notes = $notes !== null && trim($notes) !== ''
                ? trim($notes)
                : ($cleanDecision === 'UPHELD' ? 'Réclamation validée par l\'arbitrage.' : 'Réclamation rejetée — caution acquise à la Trésorerie.');
            $lockedDispute->save();

            return [
                'dispute' => $lockedDispute->fresh(['tournament', 'challenger', 'resolvedByAdmin']),
                'wallet' => $lockedChallengerWallet,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Verify that a wallet's balance_cents strictly equals SUM(credit_cents) - SUM(debit_cents).
     */
    public function verifyWalletLedgerInvariant(Wallet $wallet): bool
    {
        $freshWallet = Wallet::findOrFail($wallet->id);
        $credits = (int) LedgerEntry::where('wallet_id', $freshWallet->id)->sum('credit_cents');
        $debits = (int) LedgerEntry::where('wallet_id', $freshWallet->id)->sum('debit_cents');

        return (int) $freshWallet->balance_cents === ($credits - $debits);
    }
}
