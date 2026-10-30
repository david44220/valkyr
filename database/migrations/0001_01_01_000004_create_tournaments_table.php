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
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('slug', 140)->unique();
            $table->string('title', 160);
            $table->string('game', 80);
            $table->string('format', 20)->default('5v5');
            $table->string('status', 24)->default('OPEN');
            $table->bigInteger('entry_fee_cents');
            $table->bigInteger('escrow_pool_cents')->default(0);
            $table->bigInteger('sponsored_guarantee_cents')->default(0);
            $table->integer('rake_bps')->default(800);
            $table->bigInteger('platform_rake_cents')->default(0);
            $table->bigInteger('net_prize_pool_cents')->default(0);
            $table->integer('max_participants')->default(16);
            $table->integer('registered_count')->default(0);
            $table->foreignId('first_place_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('second_place_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('third_place_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('settlement_idempotency_key', 120)->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'game']);
            $table->index('entry_fee_cents');
            $table->index('organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
