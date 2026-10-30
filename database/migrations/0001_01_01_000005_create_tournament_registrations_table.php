<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournament_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')
                ->constrained('tournaments')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('loadout_class', 40)->default('VANGUARD');
            $table->bigInteger('paid_entry_fee_cents');
            $table->bigInteger('prize_won_cents')->default(0);
            $table->string('status', 24)->default('REGISTERED');
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id']);
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_registrations');
    }
};
