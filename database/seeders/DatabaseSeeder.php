<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentRail;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Services\EscrowLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with deterministic accounts, wallets, payment rails,
     * saved payment methods, tournaments, and all 5 revenue streams on the double-entry ledger.
     */
    public function run(): void
    {
        $ledgerService = app(EscrowLedgerService::class);
        $ledgerService->ensureSystemWallets();
        $ledgerService->ensureDefaultPaymentRails();

        $defaultPassword = Hash::make('Password123!');

        // 1. Core Operators & Competitive Players
        $admin = User::updateOrCreate(
            ['email' => 'admin@valkyr.arena'],
            [
                'name' => 'Commandant Souverain VALKYR',
                'gamertag' => 'VKR_COMMANDER',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN,
                'elo_rating' => 2450,
                'preferred_class' => 'VALKYRIE',
                'is_prime' => true,
                'prime_expires_at' => now()->addYear(),
                'is_frozen' => false,
                'kyc_status' => User::KYC_INSTITUTIONAL,
            ]
        );

        $organizer = User::updateOrCreate(
            ['email' => 'organizer@valkyr.arena'],
            [
                'name' => 'Arbitre Officiel Ligue Pro',
                'gamertag' => 'VKR_ARBITER',
                'password' => $defaultPassword,
                'role' => User::ROLE_ORGANIZER,
                'elo_rating' => 2180,
                'preferred_class' => 'CHRONO',
                'is_prime' => false,
                'is_frozen' => false,
                'kyc_status' => User::KYC_VERIFIED_PRO,
            ]
        );

        $rivalOrganizer = User::updateOrCreate(
            ['email' => 'rival.org@valkyr.arena'],
            [
                'name' => 'Directeur Circuit Rival',
                'gamertag' => 'RIVAL_HOST',
                'password' => $defaultPassword,
                'role' => User::ROLE_ORGANIZER,
                'elo_rating' => 1920,
                'preferred_class' => 'VANGUARD',
                'is_prime' => false,
                'is_frozen' => false,
                'kyc_status' => User::KYC_VERIFIED_PRO,
            ]
        );

        $nyx = User::updateOrCreate(
            ['email' => 'nyx@valkyr.arena'],
            [
                'name' => 'Elena "Nyx" Vance',
                'gamertag' => 'VKR_NYX',
                'password' => $defaultPassword,
                'role' => User::ROLE_PLAYER,
                'elo_rating' => 2365,
                'preferred_class' => 'VALKYRIE',
                'is_prime' => false,
                'is_frozen' => false,
                'kyc_status' => User::KYC_VERIFIED_PRO,
            ]
        );

        $kestrel = User::updateOrCreate(
            ['email' => 'kestrel@valkyr.arena'],
            [
                'name' => 'Soren "Kestrel" Lindqvist',
                'gamertag' => 'KESTREL_ZERO',
                'password' => $defaultPassword,
                'role' => User::ROLE_PLAYER,
                'elo_rating' => 2300,
                'preferred_class' => 'SPECTRE',
                'is_prime' => false,
                'is_frozen' => false,
                'kyc_status' => User::KYC_VERIFIED_PRO,
            ]
        );

        $aether = User::updateOrCreate(
            ['email' => 'aether@valkyr.arena'],
            [
                'name' => 'Kaito "Aether" Takahashi',
                'gamertag' => 'AETHER_9',
                'password' => $defaultPassword,
                'role' => User::ROLE_PLAYER,
                'elo_rating' => 2235,
                'preferred_class' => 'CHRONO',
                'is_prime' => false,
                'is_frozen' => false,
                'kyc_status' => User::KYC_STANDARD,
            ]
        );

        $sovereign = User::updateOrCreate(
            ['email' => 'sovereign@valkyr.arena'],
            [
                'name' => 'Marcus "Sovereign" Sterling',
                'gamertag' => 'SOVEREIGN_X',
                'password' => $defaultPassword,
                'role' => User::ROLE_PLAYER,
                'elo_rating' => 2195,
                'preferred_class' => 'VANGUARD',
                'is_prime' => false,
                'is_frozen' => false,
                'kyc_status' => User::KYC_STANDARD,
            ]
        );

        // 2. Saved Payment Methods for Demo Users
        $sepaInstantRail = PaymentRail::where('code', 'SEPA_INSTANT')->first();
        $sepaManualRail = PaymentRail::where('code', 'SEPA_MANUAL')->first();
        $cryptoRail = PaymentRail::where('code', 'CRYPTO_USDT')->first();
        $cardRail = PaymentRail::where('code', 'STRIPE_CARD')->first();

        if ($sepaInstantRail !== null) {
            UserPaymentMethod::updateOrCreate(
                ['user_id' => $nyx->id, 'label' => 'Compte Principal BNP Paribas SEPA'],
                [
                    'payment_rail_id' => $sepaInstantRail->id,
                    'account_identifier' => 'FR76 3000 4028 3700 0108 8291 044',
                    'is_default' => true,
                    'is_verified' => true,
                ]
            );
            UserPaymentMethod::updateOrCreate(
                ['user_id' => $organizer->id, 'label' => 'Trésorerie Pro VKR_ARBITER SEPA'],
                [
                    'payment_rail_id' => $sepaInstantRail->id,
                    'account_identifier' => 'FR76 1027 8060 3900 0209 4410 382',
                    'is_default' => true,
                    'is_verified' => true,
                ]
            );
            UserPaymentMethod::updateOrCreate(
                ['user_id' => $aether->id, 'label' => 'Compte Courant Société Générale'],
                [
                    'payment_rail_id' => $sepaInstantRail->id,
                    'account_identifier' => 'FR76 3000 3035 4000 0502 1847 219',
                    'is_default' => true,
                    'is_verified' => true,
                ]
            );
        }

        if ($cryptoRail !== null) {
            UserPaymentMethod::updateOrCreate(
                ['user_id' => $nyx->id, 'label' => 'Ledger Nano X // Arbitrum Vault'],
                [
                    'payment_rail_id' => $cryptoRail->id,
                    'account_identifier' => '0x71C95911E9a5D330f4D621842EC243EE1343292e',
                    'is_default' => false,
                    'is_verified' => true,
                ]
            );
            UserPaymentMethod::updateOrCreate(
                ['user_id' => $kestrel->id, 'label' => 'Cold Wallet Polygon EURC'],
                [
                    'payment_rail_id' => $cryptoRail->id,
                    'account_identifier' => '0x4B20993Bc481177ec7E8f571ceCaE8A9e22C02db',
                    'is_default' => true,
                    'is_verified' => true,
                ]
            );
        }

        // 3. Initial Wallet Deposits via Double-Entry Ledger Service
        $ledgerService->deposit($admin, 500000, 'seed:deposit:admin:01', 'Dotation initiale Trésorerie Commandement');
        $ledgerService->deposit($organizer, 250000, 'seed:deposit:organizer:01', 'Dotation initiale Organisateur Certifié');
        $ledgerService->deposit($rivalOrganizer, 100000, 'seed:deposit:rival:01', 'Dotation initiale Organisateur Secondaire');

        // Players receive initial deposits so that after tournament buy-ins and settlements their balances are abundant
        $ledgerService->deposit($nyx, 180000, 'seed:deposit:nyx:01', 'Dépôt initial vérifié SEPA Instant // VKR_NYX', $sepaInstantRail?->id, 'SEPA-NYX-001');
        $ledgerService->deposit($kestrel, 150000, 'seed:deposit:kestrel:01', 'Dépôt initial vérifié SEPA Instant // KESTREL_ZERO', $sepaInstantRail?->id, 'SEPA-KES-001');
        // Card deposit by KESTREL_ZERO generating Payment Rail Fee revenue (1.5% + 0.35€ = 1.85€ -> 185 cents to SYS_TREASURY)
        $ledgerService->deposit($kestrel, 10000, 'seed:deposit:kestrel:card02', 'Rechargement Carte 3D-Secure // KESTREL_ZERO', $cardRail?->id, 'VISA-3DS-9841');
        $ledgerService->deposit($aether, 145000, 'seed:deposit:aether:01', 'Dépôt initial vérifié SEPA Instant // AETHER_9', $sepaInstantRail?->id, 'SEPA-AET-001');
        $ledgerService->deposit($sovereign, 125000, 'seed:deposit:sovereign:01', 'Dépôt initial vérifié SEPA Instant // SOVEREIGN_X', $sepaInstantRail?->id, 'SEPA-SOV-001');

        // 4. Revenue Stream #3: VKR_NYX subscribes to ★ VALKYR PRIME (19,00 € from Wallet to SYS_TREASURY)
        $ledgerService->subscribePrime($nyx, 'seed:prime:nyx:01');

        // 5. Tournament 1 — OPEN & FEATURED (Owned by VKR_ARBITER, 3 players registered + Sponsor Pool + Arena Boost)
        $t1 = Tournament::updateOrCreate(
            ['slug' => 'valkyr-sovereign-series-s04'],
            [
                'organizer_id' => $organizer->id,
                'title' => 'VALKYR SOVEREIGN SERIES // S04',
                'game' => 'VALORANT // PROTOCOL',
                'format' => '5v5',
                'status' => Tournament::STATUS_OPEN,
                'entry_fee_cents' => 10000, // 100,00 €
                'escrow_pool_cents' => 0,
                'sponsored_guarantee_cents' => 50000,
                'rake_bps' => 800,
                'organizer_share_bps' => 200,
                'platform_rake_cents' => 0,
                'organizer_rake_cents' => 0,
                'net_prize_pool_cents' => 0,
                'is_featured' => false,
                'boost_paid_cents' => 0,
                'max_participants' => 16,
                'registered_count' => 0,
                'starts_at' => now()->addHours(4),
            ]
        );
        $ledgerService->registerForTournament($nyx, $t1, 'seed:reg:t1:nyx', 'VALKYRIE');
        $ledgerService->registerForTournament($kestrel, $t1, 'seed:reg:t1:kestrel', 'SPECTRE');
        $ledgerService->registerForTournament($aether, $t1, 'seed:reg:t1:aether', 'CHRONO');

        // Revenue Stream #4: VKR_ARBITER boosts Tournament 1 (25,00 € from Organizer Wallet to SYS_TREASURY)
        $ledgerService->boostTournament($organizer, $t1, 'seed:boost:t1:arbiter');

        // Sponsor Pool Injection: VKR_COMMANDER injects 200,00 € (20000 cents) from Wallet into T1 Escrow Vault
        $ledgerService->sponsorTournament($admin, $t1, 20000, 'seed:sponsor:t1:admin');

        // 6. Tournament 2 — OPEN (CS2 Tactical Invitational, open to NYX & AETHER)
        $t2 = Tournament::updateOrCreate(
            ['slug' => 'aegis-tactical-invitational-eu'],
            [
                'organizer_id' => $organizer->id,
                'title' => 'AEGIS TACTICAL INVITATIONAL // EU-WEST',
                'game' => 'COUNTER-STRIKE 2',
                'format' => '5v5',
                'status' => Tournament::STATUS_OPEN,
                'entry_fee_cents' => 5000, // 50,00 €
                'escrow_pool_cents' => 0,
                'sponsored_guarantee_cents' => 25000,
                'rake_bps' => 800,
                'organizer_share_bps' => 200,
                'platform_rake_cents' => 0,
                'organizer_rake_cents' => 0,
                'net_prize_pool_cents' => 0,
                'max_participants' => 8,
                'registered_count' => 0,
                'starts_at' => now()->addHours(8),
            ]
        );
        $ledgerService->registerForTournament($kestrel, $t2, 'seed:reg:t2:kestrel', 'SPECTRE');
        $ledgerService->registerForTournament($sovereign, $t2, 'seed:reg:t2:sovereign', 'VANGUARD');

        // 7. Tournament 3 — OPEN (1v1 High-Velocity Duel)
        $t3 = Tournament::updateOrCreate(
            ['slug' => 'duel-souverain-1v1-titanium-cup'],
            [
                'organizer_id' => $organizer->id,
                'title' => 'DUEL SOUVERAIN // 1V1 TITANIUM CUP',
                'game' => 'APEX // SOVEREIGN',
                'format' => '1v1',
                'status' => Tournament::STATUS_OPEN,
                'entry_fee_cents' => 2500, // 25,00 €
                'escrow_pool_cents' => 0,
                'sponsored_guarantee_cents' => 10000,
                'rake_bps' => 800,
                'organizer_share_bps' => 200,
                'platform_rake_cents' => 0,
                'organizer_rake_cents' => 0,
                'net_prize_pool_cents' => 0,
                'max_participants' => 8,
                'registered_count' => 0,
                'starts_at' => now()->addHours(12),
            ]
        );
        $ledgerService->registerForTournament($aether, $t3, 'seed:reg:t3:aether', 'CHRONO');
        $ledgerService->registerForTournament($sovereign, $t3, 'seed:reg:t3:sovereign', 'VANGUARD');

        // 8. Tournament 4 — OPEN (Owned by RIVAL_HOST for Cross-Organizer Anti-IDOR verification)
        $t4 = Tournament::updateOrCreate(
            ['slug' => 'nexus-rival-circuit-div-1'],
            [
                'organizer_id' => $rivalOrganizer->id,
                'title' => 'NEXUS RIVAL CIRCUIT // DIVISION I',
                'game' => 'LEAGUE OF LEGENDS',
                'format' => '5v5',
                'status' => Tournament::STATUS_OPEN,
                'entry_fee_cents' => 5000, // 50,00 €
                'escrow_pool_cents' => 0,
                'sponsored_guarantee_cents' => 20000,
                'rake_bps' => 800,
                'organizer_share_bps' => 200,
                'platform_rake_cents' => 0,
                'organizer_rake_cents' => 0,
                'net_prize_pool_cents' => 0,
                'max_participants' => 16,
                'registered_count' => 0,
                'starts_at' => now()->addHours(18),
            ]
        );
        $ledgerService->registerForTournament($nyx, $t4, 'seed:reg:t4:nyx', 'VALKYRIE');
        $ledgerService->registerForTournament($aether, $t4, 'seed:reg:t4:aether', 'CHRONO');
        $ledgerService->registerForTournament($sovereign, $t4, 'seed:reg:t4:sovereign', 'VANGUARD');

        // 9. Tournament 5 — IN_PROGRESS High-Stakes Major (1 000,00 € locked in escrow)
        $t5 = Tournament::updateOrCreate(
            ['slug' => 'orion-grand-slam-protocole-elite'],
            [
                'organizer_id' => $organizer->id,
                'title' => 'ORION GRAND SLAM // PROTOCOLE ÉLITE',
                'game' => 'VALORANT // PROTOCOL',
                'format' => '5v5',
                'status' => Tournament::STATUS_OPEN,
                'entry_fee_cents' => 25000, // 250,00 €
                'escrow_pool_cents' => 0,
                'sponsored_guarantee_cents' => 100000,
                'rake_bps' => 800,
                'organizer_share_bps' => 200,
                'platform_rake_cents' => 0,
                'organizer_rake_cents' => 0,
                'net_prize_pool_cents' => 0,
                'max_participants' => 16,
                'registered_count' => 0,
                'starts_at' => now()->subMinutes(42),
            ]
        );
        $ledgerService->registerForTournament($nyx, $t5, 'seed:reg:t5:nyx', 'VALKYRIE');
        $ledgerService->registerForTournament($kestrel, $t5, 'seed:reg:t5:kestrel', 'SPECTRE');
        $ledgerService->registerForTournament($aether, $t5, 'seed:reg:t5:aether', 'CHRONO');
        $ledgerService->registerForTournament($sovereign, $t5, 'seed:reg:t5:sovereign', 'VANGUARD');
        $t5->status = Tournament::STATUS_IN_PROGRESS;
        $t5->save();

        // 10. Tournament 6 — SETTLED Archive (400,00 € Pool -> 24,00 € Platform Rake (6%) + 8,00 € Organizer RevShare (2%) + 368,00 € Net Distributed 60/30/10)
        $t6 = Tournament::updateOrCreate(
            ['slug' => 'valkyr-genesis-major-finale'],
            [
                'organizer_id' => $organizer->id,
                'title' => 'VALKYR GENESIS MAJOR // FINALE ARCHIVÉE',
                'game' => 'COUNTER-STRIKE 2',
                'format' => '5v5',
                'status' => Tournament::STATUS_OPEN,
                'entry_fee_cents' => 10000, // 100,00 €
                'escrow_pool_cents' => 0,
                'sponsored_guarantee_cents' => 0,
                'rake_bps' => 800,
                'organizer_share_bps' => 200,
                'platform_rake_cents' => 0,
                'organizer_rake_cents' => 0,
                'net_prize_pool_cents' => 0,
                'max_participants' => 16,
                'registered_count' => 0,
                'starts_at' => now()->subDays(2),
            ]
        );
        $ledgerService->registerForTournament($nyx, $t6, 'seed:reg:t6:nyx', 'VALKYRIE');
        $ledgerService->registerForTournament($kestrel, $t6, 'seed:reg:t6:kestrel', 'SPECTRE');
        $ledgerService->registerForTournament($aether, $t6, 'seed:reg:t6:aether', 'CHRONO');
        $ledgerService->registerForTournament($sovereign, $t6, 'seed:reg:t6:sovereign', 'VANGUARD');

        $ledgerService->settleTournament(
            actor: $organizer,
            tournament: $t6,
            firstPlaceUserId: $nyx->id,
            secondPlaceUserId: $kestrel->id,
            thirdPlaceUserId: $aether->id,
            idempotencyKey: 'seed:settle:t6:official'
        );

        // 11. Pending Manual Funding Requests (SEPA_MANUAL_WIRE) for 1-click Admin Approval in /admin
        if ($sepaManualRail !== null) {
            $ledgerService->deposit(
                user: $kestrel,
                amountCents: 50000, // 500,00 €
                idempotencyKey: 'seed:manual:deposit:kestrel:01',
                description: 'Virement SEPA Manuel Institutionnel // KESTREL_ZERO',
                paymentRailId: $sepaManualRail->id,
                referenceCode: 'VKR-SEPA-WIRE-8842'
            );

            $ledgerService->withdraw(
                user: $aether,
                amountCents: 15000, // 150,00 €
                idempotencyKey: 'seed:manual:withdraw:aether:01',
                iban: 'FR76 3000 3035 4000 0502 1847 219',
                description: 'Retrait SEPA Trésorerie Manuel // AETHER_9',
                paymentRailId: $sepaManualRail->id
            );
        }
    }
}
