<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Wallet;
use App\Services\EscrowLedgerService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EscrowLedgerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_database_maintains_strict_double_entry_conservation_on_all_wallets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $ledgerService = app(EscrowLedgerService::class);
        $wallets = Wallet::all();

        $this->assertGreaterThanOrEqual(9, $wallets->count());

        foreach ($wallets as $wallet) {
            $this->assertTrue(
                $ledgerService->verifyWalletLedgerInvariant($wallet),
                "Wallet #{$wallet->id} (user_id={$wallet->user_id}) failed SUM(credits) - SUM(debits) === balance_cents invariant."
            );
            $this->assertGreaterThanOrEqual(0, $wallet->balance_cents);
            $this->assertGreaterThanOrEqual(0, $wallet->locked_escrow_cents);
        }

        // Verify that total credits across all ledger entries match total debits + total initial deposits
        $totalDeposits = (int) LedgerEntry::where('entry_type', LedgerEntry::TYPE_DEPOSIT)->sum('credit_cents');
        $sumAllWalletBalances = (int) Wallet::sum('balance_cents');

        $this->assertSame($totalDeposits, $sumAllWalletBalances);
    }

    public function test_public_registration_prevents_role_and_elo_mass_assignment(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Intruder Operator',
            'gamertag' => 'INTRUDER_99',
            'email' => 'intruder@valkyr.arena',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'preferred_class' => 'SPECTRE',
            'role' => 'ADMIN',
            'elo_rating' => 9999,
            'balance_cents' => 5000000,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'intruder@valkyr.arena')->firstOrFail();
        $this->assertSame(User::ROLE_PLAYER, $user->role);
        $this->assertSame(1500, $user->elo_rating);
        $this->assertSame(0, $user->wallet->balance_cents);
    }

    public function test_wallet_deposit_enforces_integer_cents_and_idempotency(): void
    {
        $player = User::factory()->player()->create();

        // Reject float amount
        $this->actingAs($player)->postJson('/api/wallets/deposit', [
            'amount_cents' => 125.50,
            'idempotency_key' => 'dep-float-test',
        ])->assertStatus(422);

        // Reject negative amount
        $this->actingAs($player)->postJson('/api/wallets/deposit', [
            'amount_cents' => -5000,
            'idempotency_key' => 'dep-neg-test',
        ])->assertStatus(422);

        // Valid integer deposit
        $firstResponse = $this->actingAs($player)->postJson('/api/wallets/deposit', [
            'amount_cents' => 25000,
            'idempotency_key' => 'dep-valid-key-001',
        ]);

        $firstResponse->assertCreated()
            ->assertJsonPath('wallet.balance_cents', 25000)
            ->assertJsonPath('idempotent_replay', false);

        // Idempotent replay with same key and same amount -> 200 OK without double credit
        $replayResponse = $this->actingAs($player)->postJson('/api/wallets/deposit', [
            'amount_cents' => 25000,
            'idempotency_key' => 'dep-valid-key-001',
        ]);

        $replayResponse->assertOk()
            ->assertJsonPath('wallet.balance_cents', 25000)
            ->assertJsonPath('idempotent_replay', true);

        // Reusing same key with different amount -> 409 Conflict
        $this->actingAs($player)->postJson('/api/wallets/deposit', [
            'amount_cents' => 50000,
            'idempotency_key' => 'dep-valid-key-001',
        ])->assertStatus(409);

        $this->assertSame(25000, $player->wallet->fresh()->balance_cents);
        $this->assertSame(1, LedgerEntry::where('wallet_id', $player->wallet->id)->count());
    }

    public function test_wallet_policy_blocks_cross_user_idor_inspection(): void
    {
        $ledgerService = app(EscrowLedgerService::class);
        $playerA = User::factory()->player()->create();
        $playerB = User::factory()->player()->create();
        $organizer = User::factory()->organizer()->create();
        $admin = User::factory()->admin()->create();

        $walletA = $ledgerService->getOrCreateWallet($playerA);
        $walletB = $ledgerService->getOrCreateWallet($playerB);

        // Player A can view own wallet
        $this->actingAs($playerA)->getJson("/api/wallets/{$walletA->id}")->assertOk();

        // Player A cannot view Player B's wallet (403 Forbidden)
        $this->actingAs($playerA)->getJson("/api/wallets/{$walletB->id}")->assertForbidden();

        // Organizer cannot view Player B's wallet (403 Forbidden)
        $this->actingAs($organizer)->getJson("/api/wallets/{$walletB->id}")->assertForbidden();

        // Admin can view Player B's wallet
        $this->actingAs($admin)->getJson("/api/wallets/{$walletB->id}")->assertOk();
    }

    public function test_tournament_buy_in_escrow_locks_funds_and_prevents_overdraft_or_duplicate_registration(): void
    {
        $ledgerService = app(EscrowLedgerService::class);
        $organizer = User::factory()->organizer()->create();
        $player1 = User::factory()->player()->create();
        $player2 = User::factory()->player()->create();
        $player3 = User::factory()->player()->create();

        $ledgerService->deposit($player1, 10000, 'dep-p1'); // 100.00 EUR
        $ledgerService->deposit($player2, 4000, 'dep-p2');  // 40.00 EUR (insufficient for 50.00 EUR buy-in)
        $ledgerService->deposit($player3, 10000, 'dep-p3'); // 100.00 EUR

        // Player cannot create a tournament (403 Forbidden)
        $this->actingAs($player1)->postJson('/api/tournaments', [
            'title' => 'Unauthorized Cup',
            'game' => 'VALORANT // PROTOCOL',
            'format' => '5v5',
            'entry_fee_cents' => 5000,
            'max_participants' => 2,
        ])->assertForbidden();

        // Organizer creates a 2-slot tournament with 50.00 EUR (5000 cents) buy-in
        $createRes = $this->actingAs($organizer)->postJson('/api/tournaments', [
            'title' => 'Tactical Duel 50',
            'game' => 'VALORANT // PROTOCOL',
            'format' => '1v1',
            'entry_fee_cents' => 5000,
            'max_participants' => 2,
        ])->assertCreated();

        $tournamentId = (int) $createRes->json('tournament.id');

        // Player 2 (4000 cents) fails post-lock sufficiency check -> 422
        $this->actingAs($player2)->postJson("/api/tournaments/{$tournamentId}/register", [
            'idempotency_key' => 'reg-p2-fail',
            'loadout_class' => 'SPECTRE',
        ])->assertStatus(422);

        $this->assertSame(4000, $player2->wallet->fresh()->balance_cents);

        // Player 1 registers successfully
        $this->actingAs($player1)->postJson("/api/tournaments/{$tournamentId}/register", [
            'idempotency_key' => 'reg-p1-ok',
            'loadout_class' => 'VALKYRIE',
        ])->assertOk()
            ->assertJsonPath('wallet.balance_cents', 5000)
            ->assertJsonPath('wallet.locked_escrow_cents', 5000)
            ->assertJsonPath('tournament.escrow_pool_cents', 5000)
            ->assertJsonPath('tournament.registered_count', 1);

        // Idempotent replay with same key returns 200 OK without double-debiting
        $this->actingAs($player1)->postJson("/api/tournaments/{$tournamentId}/register", [
            'idempotency_key' => 'reg-p1-ok',
            'loadout_class' => 'VALKYRIE',
        ])->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('wallet.balance_cents', 5000);

        // Duplicate registration attempt by Player 1 with a new key -> 409 Conflict
        $this->actingAs($player1)->postJson("/api/tournaments/{$tournamentId}/register", [
            'idempotency_key' => 'reg-p1-duplicate-attempt',
            'loadout_class' => 'VALKYRIE',
        ])->assertStatus(409);

        // Player 3 takes the 2nd (final) slot -> tournament automatically locks (STATUS_LOCKED)
        $this->actingAs($player3)->postJson("/api/tournaments/{$tournamentId}/register", [
            'idempotency_key' => 'reg-p3-ok',
            'loadout_class' => 'CHRONO',
        ])->assertOk()
            ->assertJsonPath('tournament.escrow_pool_cents', 10000)
            ->assertJsonPath('tournament.registered_count', 2)
            ->assertJsonPath('tournament.status', Tournament::STATUS_LOCKED);

        // Player 2 tops up wallet and tries to join full/locked tournament -> 409 Conflict
        $ledgerService->deposit($player2, 5000, 'dep-p2-topup');
        $this->actingAs($player2)->postJson("/api/tournaments/{$tournamentId}/register", [
            'idempotency_key' => 'reg-p2-late',
        ])->assertStatus(409);
    }

    public function test_tournament_settlement_enforces_anti_idor_8_percent_rake_and_60_30_10_conservation(): void
    {
        $ledgerService = app(EscrowLedgerService::class);

        $ownerOrganizer = User::factory()->organizer()->create();
        $rivalOrganizer = User::factory()->organizer()->create();
        $p1 = User::factory()->player()->create(['elo_rating' => 2000]);
        $p2 = User::factory()->player()->create(['elo_rating' => 2000]);
        $p3 = User::factory()->player()->create(['elo_rating' => 2000]);
        $nonParticipant = User::factory()->player()->create();

        // Use an entry fee that produces non-trivial integer division remainders: 3335 cents * 3 = 10005 cents
        $entryFee = 3335;
        $ledgerService->deposit($p1, 10000, 'dep-settle-p1');
        $ledgerService->deposit($p2, 10000, 'dep-settle-p2');
        $ledgerService->deposit($p3, 10000, 'dep-settle-p3');

        $tournament = Tournament::create([
            'organizer_id' => $ownerOrganizer->id,
            'slug' => 'precision-conservation-test',
            'title' => 'PRECISION CONSERVATION TEST',
            'game' => 'COUNTER-STRIKE 2',
            'format' => '5v5',
            'status' => Tournament::STATUS_OPEN,
            'entry_fee_cents' => $entryFee,
            'escrow_pool_cents' => 0,
            'sponsored_guarantee_cents' => 0,
            'rake_bps' => 800,
            'platform_rake_cents' => 0,
            'net_prize_pool_cents' => 0,
            'max_participants' => 8,
            'registered_count' => 0,
        ]);

        $ledgerService->registerForTournament($p1, $tournament, 'reg-settle-p1');
        $ledgerService->registerForTournament($p2, $tournament, 'reg-settle-p2');
        $ledgerService->registerForTournament($p3, $tournament, 'reg-settle-p3');

        // 1. Player attempting to settle -> 403 Forbidden
        $this->actingAs($p1)->postJson("/api/tournaments/{$tournament->id}/settle", [
            'first_place_user_id' => $p1->id,
            'second_place_user_id' => $p2->id,
            'third_place_user_id' => $p3->id,
            'idempotency_key' => 'hack-settle-player',
        ])->assertForbidden();

        // 2. Rival Organizer (Anti-IDOR) attempting to settle -> 403 Forbidden
        $this->actingAs($rivalOrganizer)->postJson("/api/tournaments/{$tournament->id}/settle", [
            'first_place_user_id' => $p1->id,
            'second_place_user_id' => $p2->id,
            'third_place_user_id' => $p3->id,
            'idempotency_key' => 'hack-settle-rival',
        ])->assertForbidden();

        // 3. Owning Organizer attempting to award prize to non-participant -> 422 Unprocessable Entity
        $this->actingAs($ownerOrganizer)->postJson("/api/tournaments/{$tournament->id}/settle", [
            'first_place_user_id' => $nonParticipant->id,
            'second_place_user_id' => $p2->id,
            'third_place_user_id' => $p3->id,
            'idempotency_key' => 'invalid-winner-settle',
        ])->assertStatus(422);

        // 4. Legitimate settlement by Owning Organizer
        // Total Pool = 3335 * 3 = 10005 cents
        // 8% Rake = intdiv(10005 * 800, 10000) = 800 cents
        // Net Pool = 10005 - 800 = 9205 cents
        // 2nd (30%) = intdiv(9205 * 3000, 10000) = 2761 cents
        // 3rd (10%) = intdiv(9205 * 1000, 10000) = 920 cents
        // 1st (60% + remainder) = 9205 - 2761 - 920 = 5524 cents
        // Check: 800 + 5524 + 2761 + 920 === 10005 cents!
        $settleRes = $this->actingAs($ownerOrganizer)->postJson("/api/tournaments/{$tournament->id}/settle", [
            'first_place_user_id' => $p1->id,
            'second_place_user_id' => $p2->id,
            'third_place_user_id' => $p3->id,
            'idempotency_key' => 'official-settle-001',
        ]);

        $settleRes->assertOk()
            ->assertJsonPath('tournament.status', Tournament::STATUS_SETTLED)
            ->assertJsonPath('breakdown.total_pool_cents', 10005)
            ->assertJsonPath('breakdown.platform_rake_cents', 800)
            ->assertJsonPath('breakdown.net_prize_pool_cents', 9205)
            ->assertJsonPath('breakdown.first_prize_cents', 5524)
            ->assertJsonPath('breakdown.second_prize_cents', 2761)
            ->assertJsonPath('breakdown.third_prize_cents', 920);

        // 5. Attempting a second settlement with a new key -> 409 Conflict (no double payout)
        $this->actingAs($ownerOrganizer)->postJson("/api/tournaments/{$tournament->id}/settle", [
            'first_place_user_id' => $p1->id,
            'second_place_user_id' => $p2->id,
            'third_place_user_id' => $p3->id,
            'idempotency_key' => 'double-settle-attempt-002',
        ])->assertStatus(409);

        // Verify wallet balances and ledger invariants
        $this->assertSame(10000 - 3335 + 5524, $p1->wallet->fresh()->balance_cents);
        $this->assertSame(10000 - 3335 + 2761, $p2->wallet->fresh()->balance_cents);
        $this->assertSame(10000 - 3335 + 920, $p3->wallet->fresh()->balance_cents);
        $this->assertSame(0, $p1->wallet->fresh()->locked_escrow_cents);
        $this->assertSame(0, $p2->wallet->fresh()->locked_escrow_cents);
        $this->assertSame(0, $p3->wallet->fresh()->locked_escrow_cents);

        $systemWallets = $ledgerService->ensureSystemWallets();
        $this->assertSame(0, $systemWallets['escrow']->fresh()->balance_cents);
        $this->assertSame(800, $systemWallets['treasury']->fresh()->balance_cents);

        foreach (Wallet::all() as $wallet) {
            $this->assertTrue($ledgerService->verifyWalletLedgerInvariant($wallet));
        }
    }

    public function test_tournament_cancellation_refunds_100_percent_of_escrow_with_zero_fee(): void
    {
        $ledgerService = app(EscrowLedgerService::class);
        $organizer = User::factory()->organizer()->create();
        $rival = User::factory()->organizer()->create();
        $p1 = User::factory()->player()->create();
        $p2 = User::factory()->player()->create();

        $ledgerService->deposit($p1, 20000, 'dep-cancel-p1');
        $ledgerService->deposit($p2, 20000, 'dep-cancel-p2');

        $tournament = Tournament::create([
            'organizer_id' => $organizer->id,
            'slug' => 'cancelled-arena-test',
            'title' => 'CANCELLED ARENA TEST',
            'game' => 'APEX // SOVEREIGN',
            'format' => '1v1',
            'status' => Tournament::STATUS_OPEN,
            'entry_fee_cents' => 7500,
            'escrow_pool_cents' => 0,
            'sponsored_guarantee_cents' => 0,
            'rake_bps' => 800,
            'platform_rake_cents' => 0,
            'net_prize_pool_cents' => 0,
            'max_participants' => 4,
            'registered_count' => 0,
        ]);

        $ledgerService->registerForTournament($p1, $tournament, 'reg-cancel-p1');
        $ledgerService->registerForTournament($p2, $tournament, 'reg-cancel-p2');

        $this->assertSame(12500, $p1->wallet->fresh()->balance_cents);
        $this->assertSame(7500, $p1->wallet->fresh()->locked_escrow_cents);

        // Rival organizer cannot cancel -> 403 Forbidden
        $this->actingAs($rival)->postJson("/api/tournaments/{$tournament->id}/cancel", [
            'idempotency_key' => 'cancel-rival-attempt',
        ])->assertForbidden();

        // Owning organizer cancels -> 100% refund
        $this->actingAs($organizer)->postJson("/api/tournaments/{$tournament->id}/cancel", [
            'idempotency_key' => 'cancel-official-001',
            'reason' => 'Maintenance serveur anti-cheat',
        ])->assertOk()
            ->assertJsonPath('tournament.status', Tournament::STATUS_CANCELLED)
            ->assertJsonPath('tournament.escrow_pool_cents', 0)
            ->assertJsonPath('refunded_count', 2)
            ->assertJsonPath('total_refunded_cents', 15000);

        $this->assertSame(20000, $p1->wallet->fresh()->balance_cents);
        $this->assertSame(0, $p1->wallet->fresh()->locked_escrow_cents);
        $this->assertSame(20000, $p2->wallet->fresh()->balance_cents);
        $this->assertSame(0, $p2->wallet->fresh()->locked_escrow_cents);
    }

    public function test_ledger_entries_are_strictly_immutable_via_eloquent_and_sql_triggers(): void
    {
        $ledgerService = app(EscrowLedgerService::class);
        $player = User::factory()->player()->create();

        $result = $ledgerService->deposit($player, 15000, 'immutable-test-key-01');
        $entry = $result['ledger_entry'];

        // 1. Eloquent update blocked
        $eloquentUpdateBlocked = false;
        try {
            $entry->update(['credit_cents' => 999999]);
        } catch (DomainException) {
            $eloquentUpdateBlocked = true;
        }
        $this->assertTrue($eloquentUpdateBlocked, 'Eloquent LedgerEntry::update must throw DomainException.');

        // 2. Eloquent delete blocked
        $eloquentDeleteBlocked = false;
        try {
            $entry->delete();
        } catch (DomainException) {
            $eloquentDeleteBlocked = true;
        }
        $this->assertTrue($eloquentDeleteBlocked, 'Eloquent LedgerEntry::delete must throw DomainException.');

        // 3. Raw SQL query builder update blocked by SQLite trigger
        $sqlUpdateBlocked = false;
        try {
            DB::table('ledger_entries')->where('id', $entry->id)->update(['credit_cents' => 999999]);
        } catch (QueryException) {
            $sqlUpdateBlocked = true;
        }
        $this->assertTrue($sqlUpdateBlocked, 'SQL trigger must abort UPDATE on ledger_entries.');

        // 4. Raw SQL query builder delete blocked by SQLite trigger
        $sqlDeleteBlocked = false;
        try {
            DB::table('ledger_entries')->where('id', $entry->id)->delete();
        } catch (QueryException) {
            $sqlDeleteBlocked = true;
        }
        $this->assertTrue($sqlDeleteBlocked, 'SQL trigger must abort DELETE on ledger_entries.');

        $this->assertSame(15000, LedgerEntry::findOrFail($entry->id)->credit_cents);
    }

    public function test_auth_rate_limiting_blocks_brute_force_and_blocks_system_vault_login(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Direct login to internal system accounts (Escrow Vault & Platform Treasury) is strictly blocked
        $this->postJson('/api/auth/login', [
            'email' => EscrowLedgerService::ESCROW_VAULT_EMAIL,
            'password' => 'SystemVault_Unreachable_Key_99!',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => EscrowLedgerService::PLATFORM_TREASURY_EMAIL,
            'password' => 'SystemTreasury_Unreachable_Key_99!',
        ])->assertStatus(422);

        // 2. Demo login rejects non-whitelisted emails with 403 Forbidden
        $this->postJson('/api/auth/demo-login', [
            'email' => EscrowLedgerService::ESCROW_VAULT_EMAIL,
        ])->assertForbidden();

        // 3. Brute-force rate limiting (throttle:auth = 10 attempts per minute) returns 429 on 11th request
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'bruteforce.target@valkyr.arena',
                'password' => 'WrongPassword'.$i,
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'bruteforce.target@valkyr.arena',
            'password' => 'WrongPassword11',
        ])->assertStatus(429);
    }

    public function test_security_headers_sql_triggers_and_xss_sqli_resilience(): void
    {
        $ledgerService = app(EscrowLedgerService::class);
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->player()->create();
        $wallet = $ledgerService->getOrCreateWallet($player);

        // 1. Verify HTTP Security Headers on Web & API responses
        $homeRes = $this->get('/');
        $homeRes->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // 2. Verify SQLite trigger prevents negative wallet balance at DB engine level
        $negativeWalletBlocked = false;
        try {
            DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => -100]);
        } catch (QueryException) {
            $negativeWalletBlocked = true;
        }
        $this->assertTrue($negativeWalletBlocked, 'SQL trigger must abort negative balance_cents on wallets table.');

        // 3. Verify SQLite trigger prevents mathematically inconsistent ledger entry at DB engine level
        $corruptMathBlocked = false;
        try {
            DB::table('ledger_entries')->insert([
                'wallet_id' => $wallet->id,
                'tournament_id' => null,
                'actor_id' => $player->id,
                'entry_type' => LedgerEntry::TYPE_DEPOSIT,
                'debit_cents' => 0,
                'credit_cents' => 5000,
                'balance_before_cents' => 0,
                'balance_after_cents' => 999999, // Invalid math: 0 + 5000 - 0 != 999999
                'idempotency_key' => 'corrupt-math-entry-001',
                'description' => 'Corrupt entry test',
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            $corruptMathBlocked = true;
        }
        $this->assertTrue($corruptMathBlocked, 'SQL trigger must abort ledger entries violating balance_before + credit - debit == balance_after.');

        // 4. Verify XSS escaping in Blade SSR and parameterized SQLi search filter
        $this->actingAs($organizer)->postJson('/api/tournaments', [
            'title' => '<script>window.__xss=1</script> PRO LEAGUE',
            'game' => 'VALORANT // PROTOCOL',
            'format' => '5v5',
            'entry_fee_cents' => 2500,
            'max_participants' => 8,
        ])->assertCreated();

        $ssrHtml = $this->get('/')->getContent();
        $this->assertStringNotContainsString('<script>window.__xss=1</script>', (string) $ssrHtml);

        $sqliRes = $this->getJson("/api/tournaments?search=' OR 1=1 --");
        $sqliRes->assertOk()
            ->assertJsonPath('total_count', 0);
    }
}

