<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InvalidFinancialOperationException;
use App\Exceptions\TournamentStateConflictException;
use App\Models\LedgerEntry;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Models\Wallet;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class EscrowLedgerService
{
    public const string ESCROW_VAULT_EMAIL = 'escrow.vault@valkyr.arena';

    public const string PLATFORM_TREASURY_EMAIL = 'treasury@valkyr.arena';

    /**
     * Ensure system users and their corresponding wallets exist.
     *
     * @return array{escrow: Wallet, treasury: Wallet}
     */
    public function ensureSystemWallets(): array
    {
        $escrowUser = User::firstOrCreate(
            ['email' => self::ESCROW_VAULT_EMAIL],
            [
                'name' => 'VALKYR Escrow Vault',
                'gamertag' => 'SYS_ESCROW_VAULT',
                'password' => Hash::make('SystemVault_Unreachable_Key_99!'),
                'role' => User::ROLE_ADMIN,
                'elo_rating' => 3000,
                'preferred_class' => 'VANGUARD',
            ]
        );

        $treasuryUser = User::firstOrCreate(
            ['email' => self::PLATFORM_TREASURY_EMAIL],
            [
                'name' => 'VALKYR Platform Treasury',
                'gamertag' => 'SYS_TREASURY',
                'password' => Hash::make('SystemTreasury_Unreachable_Key_99!'),
                'role' => User::ROLE_ADMIN,
                'elo_rating' => 3000,
                'preferred_class' => 'VALKYRIE',
            ]
        );

        return [
            'escrow' => $this->getOrCreateWallet($escrowUser),
            'treasury' => $this->getOrCreateWallet($treasuryUser),
        ];
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
     * Deposit integer cents into a user's wallet with strict idempotency and pessimistic row locking.
     *
     * @return array{wallet: Wallet, ledger_entry: LedgerEntry, idempotent_replay: bool}
     */
    public function deposit(
        User $user,
        int $amountCents,
        string $idempotencyKey,
        string $description = 'Dépôt de fonds sur portefeuille VALKYR // ARENA'
    ): array {
        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire.');
        }

        if ($amountCents <= 0 || $amountCents > 100_000_000) {
            throw new InvalidFinancialOperationException('Le montant du dépôt en centimes doit être un entier strictement positif (entre 1 et 100 000 000 centimes).');
        }

        $initialWallet = $this->getOrCreateWallet($user);

        return DB::transaction(function () use ($user, $initialWallet, $amountCents, $trimmedKey, $description): array {
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
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Cette clé d\'idempotence a déjà été utilisée pour une transaction différente.');
            }

            $lockedWallet = Wallet::whereKey($initialWallet->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (int) $lockedWallet->balance_cents;
            $balanceAfter = $balanceBefore + $amountCents;

            $lockedWallet->balance_cents = $balanceAfter;
            $lockedWallet->save();

            $entry = LedgerEntry::create([
                'wallet_id' => $lockedWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_DEPOSIT,
                'debit_cents' => 0,
                'credit_cents' => $amountCents,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents' => $balanceAfter,
                'idempotency_key' => $trimmedKey,
                'description' => $description,
                'metadata' => [
                    'currency' => $lockedWallet->currency,
                    'gamertag' => $user->gamertag,
                ],
            ]);

            return [
                'wallet' => $lockedWallet,
                'ledger_entry' => $entry,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Withdraw integer cents from a user's wallet via SEPA Instant with strict idempotency,
     * pessimistic row locking, and post-lock sufficiency check.
     *
     * @return array{wallet: Wallet, ledger_entry: LedgerEntry, idempotent_replay: bool}
     */
    public function withdraw(
        User $user,
        int $amountCents,
        string $idempotencyKey,
        string $iban = 'FR76 3000 6000 0112 3456 7890 189',
        string $description = 'Retrait bancaire SEPA Instant depuis portefeuille VALKYR // ARENA'
    ): array {
        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '') {
            throw new InvalidFinancialOperationException('La clé d\'idempotence est obligatoire pour le retrait.');
        }

        if ($amountCents < 100 || $amountCents > 50_000_000) {
            throw new InvalidFinancialOperationException('Le montant du retrait en centimes doit être un entier compris entre 100 (1,00 €) et 50 000 000 centimes.');
        }

        $initialWallet = $this->getOrCreateWallet($user);

        return DB::transaction(function () use ($user, $initialWallet, $amountCents, $trimmedKey, $iban, $description): array {
            $existingEntry = LedgerEntry::where('idempotency_key', $trimmedKey)->first();
            if ($existingEntry !== null) {
                if (
                    (int) $existingEntry->wallet_id === (int) $initialWallet->id
                    && $existingEntry->entry_type === LedgerEntry::TYPE_WITHDRAWAL
                    && (int) $existingEntry->debit_cents === $amountCents
                ) {
                    $freshWallet = Wallet::whereKey($initialWallet->id)->firstOrFail();

                    return [
                        'wallet' => $freshWallet,
                        'ledger_entry' => $existingEntry,
                        'idempotent_replay' => true,
                    ];
                }

                throw new IdempotencyConflictException('Cette clé d\'idempotence a déjà été utilisée pour une transaction différente.');
            }

            $lockedWallet = Wallet::whereKey($initialWallet->id)->lockForUpdate()->firstOrFail();

            // Strictly AFTER acquiring pessimistic lock: verify available balance_cents >= amountCents
            if ((int) $lockedWallet->balance_cents < $amountCents) {
                throw new InsufficientFundsException(
                    sprintf(
                        'Solde disponible insuffisant (%s) pour exécuter le retrait SEPA de %s.',
                        Wallet::formatCents((int) $lockedWallet->balance_cents),
                        Wallet::formatCents($amountCents)
                    )
                );
            }

            $balanceBefore = (int) $lockedWallet->balance_cents;
            $balanceAfter = $balanceBefore - $amountCents;

            $lockedWallet->balance_cents = $balanceAfter;
            $lockedWallet->save();

            $entry = LedgerEntry::create([
                'wallet_id' => $lockedWallet->id,
                'tournament_id' => null,
                'actor_id' => $user->id,
                'entry_type' => LedgerEntry::TYPE_WITHDRAWAL,
                'debit_cents' => $amountCents,
                'credit_cents' => 0,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents' => $balanceAfter,
                'idempotency_key' => $trimmedKey,
                'description' => $description,
                'metadata' => [
                    'iban' => $iban,
                    'currency' => $lockedWallet->currency,
                    'gamertag' => $user->gamertag,
                ],
            ]);

            return [
                'wallet' => $lockedWallet,
                'ledger_entry' => $entry,
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

            // Reload escrow wallet in case player wallet === escrow wallet (defensive guard)
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
     * - Deduct 8% (800 bps) platform rake to Platform Treasury
     * - Distribute net pool: 60% (+ integer remainder) to 1st, 30% to 2nd, 10% to 3rd (or 70%/30% if 2 players)
     * - Verify strict monetary conservation: rake + 1st + 2nd + 3rd === totalPool
     *
     * @return array{
     *     tournament: Tournament,
     *     breakdown: array{
     *         total_pool_cents: int,
     *         platform_rake_cents: int,
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

            // Idempotent replay check if already settled with the exact same key and winners
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

            // Ensure all registered participants have wallets, then lock all wallets in deterministic ASC order
            $participantUserIds = $registrations->keys()->map(fn ($id): int => (int) $id)->all();
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

            $allWalletIds = [
                (int) $escrowWallet->id,
                (int) $treasuryWallet->id,
            ];
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

            // Server-side integer math: 8% (800 bps) platform rake + 60/30/10 net distribution
            $rakeBps = (int) ($lockedTournament->rake_bps ?: Tournament::DEFAULT_RAKE_BPS);
            $rakeCents = intdiv($totalPool * $rakeBps, 10000);
            $netPoolCents = $totalPool - $rakeCents;

            if ($thirdPlaceUserId !== null) {
                $secondPrize = intdiv($netPoolCents * 3000, 10000);
                $thirdPrize = intdiv($netPoolCents * 1000, 10000);
                $firstPrize = $netPoolCents - $secondPrize - $thirdPrize;
            } else {
                $secondPrize = intdiv($netPoolCents * 3000, 10000);
                $thirdPrize = 0;
                $firstPrize = $netPoolCents - $secondPrize;
            }

            if ($rakeCents + $firstPrize + $secondPrize + $thirdPrize !== $totalPool) {
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
                    'rake_cents' => $rakeCents,
                    'net_prize_pool_cents' => $netPoolCents,
                ],
            ]);

            // 2. Credit Platform Treasury with 8% commission
            if ($rakeCents > 0) {
                $treasuryBefore = (int) $lockedTreasuryWallet->balance_cents;
                $treasuryAfter = $treasuryBefore + $rakeCents;
                $lockedTreasuryWallet->balance_cents = $treasuryAfter;
                $lockedTreasuryWallet->save();

                LedgerEntry::create([
                    'wallet_id' => $lockedTreasuryWallet->id,
                    'tournament_id' => $lockedTournament->id,
                    'actor_id' => $actor->id,
                    'entry_type' => LedgerEntry::TYPE_PLATFORM_RAKE_CREDIT,
                    'debit_cents' => 0,
                    'credit_cents' => $rakeCents,
                    'balance_before_cents' => $treasuryBefore,
                    'balance_after_cents' => $treasuryAfter,
                    'idempotency_key' => "{$trimmedKey}:platform_rake",
                    'description' => "Commission plateforme 8 % (800 bps) — {$lockedTournament->title}",
                    'metadata' => [
                        'rake_bps' => $rakeBps,
                        'total_pool_cents' => $totalPool,
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

            // 5. Update ELO ratings for podium players
            User::whereKey($firstPlaceUserId)->increment('elo_rating', 25);
            User::whereKey($secondPlaceUserId)->increment('elo_rating', 10);
            if ($thirdPlaceUserId !== null) {
                User::whereKey($thirdPlaceUserId)->increment('elo_rating', 5);
            }

            // 6. Finalize tournament record
            $lockedTournament->status = Tournament::STATUS_SETTLED;
            $lockedTournament->platform_rake_cents = $rakeCents;
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
                    'platform_rake_cents' => $rakeCents,
                    'net_prize_pool_cents' => $netPoolCents,
                    'first_prize_cents' => $firstPrize,
                    'second_prize_cents' => $secondPrize,
                    'third_prize_cents' => $thirdPrize,
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

        return DB::transaction(function () use ($actor, $tournament, $trimmedKey, $reason, $escrowWallet): array {
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

            $participantUserIds = $registrations->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
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

            $allWalletIds = [(int) $escrowWallet->id];
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
