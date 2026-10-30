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
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();
            $table->foreignId('tournament_id')
                ->nullable()
                ->constrained('tournaments')
                ->restrictOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('entry_type', 40);
            $table->bigInteger('debit_cents')->default(0);
            $table->bigInteger('credit_cents')->default(0);
            $table->bigInteger('balance_before_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('idempotency_key', 140)->unique();
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
            $table->index('tournament_id');
            $table->index('actor_id');
            $table->index('entry_type');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prevent_ledger_entries_update
                BEFORE UPDATE ON ledger_entries
                BEGIN
                    SELECT RAISE(ABORT, 'Immutable ledger entry cannot be modified or deleted');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prevent_ledger_entries_delete
                BEFORE DELETE ON ledger_entries
                BEGIN
                    SELECT RAISE(ABORT, 'Immutable ledger entry cannot be modified or deleted');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER validate_ledger_entries_math
                BEFORE INSERT ON ledger_entries
                WHEN NEW.debit_cents < 0
                  OR NEW.credit_cents < 0
                  OR NEW.balance_after_cents < 0
                  OR (NEW.balance_before_cents + NEW.credit_cents - NEW.debit_cents) != NEW.balance_after_cents
                BEGIN
                    SELECT RAISE(ABORT, 'Ledger entry mathematical conservation invariant violated');
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
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_ledger_entries_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_ledger_entries_delete;');
            DB::unprepared('DROP TRIGGER IF EXISTS validate_ledger_entries_math;');
        }

        Schema::dropIfExists('ledger_entries');
    }
};
