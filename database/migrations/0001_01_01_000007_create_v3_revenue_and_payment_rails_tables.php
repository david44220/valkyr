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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_prime')->default(false)->after('preferred_class');
            $table->timestamp('prime_expires_at')->nullable()->after('is_prime');
            $table->boolean('is_frozen')->default(false)->after('prime_expires_at');
            $table->string('kyc_status', 24)->default('VERIFIED')->after('is_frozen');
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->integer('organizer_share_bps')->default(0)->after('rake_bps');
            $table->bigInteger('organizer_rake_cents')->default(0)->after('platform_rake_cents');
            $table->boolean('is_featured')->default(false)->after('registered_count');
            $table->bigInteger('boost_paid_cents')->default(0)->after('is_featured');
        });

        Schema::create('payment_rails', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('provider', 80);
            $table->string('method_type', 30)->default('BANK_TRANSFER');
            $table->boolean('supports_deposit')->default(true);
            $table->boolean('supports_withdrawal')->default(true);
            $table->boolean('requires_admin_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('deposit_fee_bps')->default(0);
            $table->bigInteger('deposit_fixed_fee_cents')->default(0);
            $table->integer('withdrawal_fee_bps')->default(0);
            $table->bigInteger('withdrawal_fixed_fee_cents')->default(0);
            $table->bigInteger('min_amount_cents')->default(500);
            $table->bigInteger('max_amount_cents')->default(10000000);
            $table->text('account_instructions')->nullable();
            $table->string('settlement_delay_label', 60)->default('INSTANTANÉ (< 5S)');
            $table->timestamps();

            $table->index(['is_active', 'supports_deposit']);
            $table->index(['is_active', 'supports_withdrawal']);
        });

        Schema::create('user_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('payment_rail_id')
                ->constrained('payment_rails')
                ->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('account_identifier', 160);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('wallet_funding_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();
            $table->foreignId('payment_rail_id')
                ->nullable()
                ->constrained('payment_rails')
                ->nullOnDelete();
            $table->foreignId('user_payment_method_id')
                ->nullable()
                ->constrained('user_payment_methods')
                ->nullOnDelete();
            $table->string('direction', 20);
            $table->bigInteger('gross_amount_cents');
            $table->bigInteger('fee_cents')->default(0);
            $table->bigInteger('net_amount_cents');
            $table->string('reference_code', 120);
            $table->string('status', 24)->default('SETTLED');
            $table->string('idempotency_key', 140)->unique();
            $table->foreignId('processed_by_admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('admin_notes', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'direction']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_funding_requests');
        Schema::dropIfExists('user_payment_methods');
        Schema::dropIfExists('payment_rails');

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropColumn([
                'organizer_share_bps',
                'organizer_rake_cents',
                'is_featured',
                'boost_paid_cents',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'is_prime',
                'prime_expires_at',
                'is_frozen',
                'kyc_status',
            ]);
        });
    }
};
