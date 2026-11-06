<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('equipped_badge', 80)->nullable()->after('preferred_class');
            $table->string('clan_tag', 12)->nullable()->after('equipped_badge');
        });

        Schema::create('wager_duels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('game', 80);
            $table->string('loadout_class', 40)->default('VALKYRIE');
            $table->bigInteger('stake_cents')->default(0);
            $table->bigInteger('escrow_pot_cents')->default(0);
            $table->integer('rake_bps')->default(800);
            $table->bigInteger('platform_rake_cents')->default(0);
            $table->bigInteger('winner_payout_cents')->default(0);
            $table->string('status', 32)->default('OPEN')->index();
            $table->string('idempotency_key', 140)->unique();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('armory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->string('name', 120);
            $table->string('category', 40)->default('BADGE_TITLE');
            $table->text('description')->nullable();
            $table->bigInteger('price_cents');
            $table->integer('elo_bonus')->default(0);
            $table->string('badge_label', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_armory_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('armory_item_id')->constrained('armory_items')->cascadeOnDelete();
            $table->bigInteger('paid_cents');
            $table->string('idempotency_key', 140)->unique();
            $table->timestamps();
            $table->unique(['user_id', 'armory_item_id']);
        });

        Schema::create('tournament_predictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('predicted_winner_user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('stake_cents');
            $table->bigInteger('payout_cents')->default(0);
            $table->string('status', 32)->default('LOCKED')->index();
            $table->string('idempotency_key', 140)->unique();
            $table->timestamps();
        });

        Schema::create('clans', function (Blueprint $table): void {
            $table->id();
            $table->string('tag', 12)->unique();
            $table->string('name', 100);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->integer('revenue_share_bps')->default(1500);
            $table->integer('member_count')->default(1);
            $table->bigInteger('license_paid_cents')->default(2900);
            $table->timestamps();
        });

        Schema::create('voucher_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->bigInteger('credit_amount_cents');
            $table->integer('max_uses')->default(10);
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_code_id')->constrained('voucher_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('credited_cents');
            $table->string('idempotency_key', 140)->unique();
            $table->timestamps();
            $table->unique(['voucher_code_id', 'user_id']);
        });

        Schema::create('tournament_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('challenger_user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('bond_cents')->default(500);
            $table->text('reason');
            $table->string('evidence_url', 255)->nullable();
            $table->string('status', 32)->default('OPEN')->index();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_resolution_notes', 255)->nullable();
            $table->string('idempotency_key', 140)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_disputes');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('voucher_codes');
        Schema::dropIfExists('clans');
        Schema::dropIfExists('tournament_predictions');
        Schema::dropIfExists('user_armory_purchases');
        Schema::dropIfExists('armory_items');
        Schema::dropIfExists('wager_duels');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['equipped_badge', 'clan_tag']);
        });
    }
};
