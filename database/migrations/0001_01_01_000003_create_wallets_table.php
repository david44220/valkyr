<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->bigInteger('balance_cents')->default(0);
            $table->bigInteger('locked_escrow_cents')->default(0);
            $table->bigInteger('lifetime_won_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->timestamps();

            $table->index('balance_cents');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prevent_negative_wallet_balance_insert
                BEFORE INSERT ON wallets
                WHEN NEW.balance_cents < 0 OR NEW.locked_escrow_cents < 0 OR NEW.lifetime_won_cents < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Wallet balance and escrow amounts cannot be negative');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prevent_negative_wallet_balance_update
                BEFORE UPDATE ON wallets
                WHEN NEW.balance_cents < 0 OR NEW.locked_escrow_cents < 0 OR NEW.lifetime_won_cents < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Wallet balance and escrow amounts cannot be negative');
                END;
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_negative_wallet_balance_insert;');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_negative_wallet_balance_update;');
        }

        Schema::dropIfExists('wallets');
    }
};
