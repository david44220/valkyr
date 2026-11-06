<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'ledger_entries', timestamps: false)]
#[WithoutTimestamps]
#[Fillable([
    'wallet_id',
    'tournament_id',
    'actor_id',
    'entry_type',
    'debit_cents',
    'credit_cents',
    'balance_before_cents',
    'balance_after_cents',
    'idempotency_key',
    'description',
    'metadata',
    'created_at',
])]
class LedgerEntry extends Model
{
    public const string TYPE_DEPOSIT = 'DEPOSIT';

    public const string TYPE_WITHDRAWAL = 'WITHDRAWAL';

    public const string TYPE_DEPOSIT_FEE_DEBIT = 'DEPOSIT_FEE_DEBIT';

    public const string TYPE_WITHDRAWAL_FEE_DEBIT = 'WITHDRAWAL_FEE_DEBIT';

    public const string TYPE_PLATFORM_PAYMENT_FEE_CREDIT = 'PLATFORM_PAYMENT_FEE_CREDIT';

    public const string TYPE_BUY_IN_ESCROW_DEBIT = 'BUY_IN_ESCROW_DEBIT';

    public const string TYPE_BUY_IN_ESCROW_CREDIT = 'BUY_IN_ESCROW_CREDIT';

    public const string TYPE_PRIZE_PAYOUT_1ST = 'PRIZE_PAYOUT_1ST';

    public const string TYPE_PRIZE_PAYOUT_2ND = 'PRIZE_PAYOUT_2ND';

    public const string TYPE_PRIZE_PAYOUT_3RD = 'PRIZE_PAYOUT_3RD';

    public const string TYPE_ESCROW_RELEASE_DEBIT = 'ESCROW_RELEASE_DEBIT';

    public const string TYPE_PLATFORM_RAKE_CREDIT = 'PLATFORM_RAKE_CREDIT';

    public const string TYPE_ORGANIZER_RAKE_SHARE_CREDIT = 'ORGANIZER_RAKE_SHARE_CREDIT';

    public const string TYPE_TOURNAMENT_REFUND_DEBIT = 'TOURNAMENT_REFUND_DEBIT';

    public const string TYPE_TOURNAMENT_REFUND_CREDIT = 'TOURNAMENT_REFUND_CREDIT';

    public const string TYPE_PRIME_SUBSCRIPTION_DEBIT = 'PRIME_SUBSCRIPTION_DEBIT';

    public const string TYPE_PRIME_SUBSCRIPTION_CREDIT = 'PRIME_SUBSCRIPTION_CREDIT';

    public const string TYPE_ARENA_BOOST_DEBIT = 'ARENA_BOOST_DEBIT';

    public const string TYPE_ARENA_BOOST_CREDIT = 'ARENA_BOOST_CREDIT';

    public const string TYPE_SPONSOR_POOL_DEBIT = 'SPONSOR_POOL_DEBIT';

    public const string TYPE_SPONSOR_POOL_CREDIT = 'SPONSOR_POOL_CREDIT';

    public const string TYPE_DUEL_ESCROW_DEBIT = 'DUEL_ESCROW_DEBIT';

    public const string TYPE_DUEL_ESCROW_CREDIT = 'DUEL_ESCROW_CREDIT';

    public const string TYPE_DUEL_PAYOUT_CREDIT = 'DUEL_PAYOUT_CREDIT';

    public const string TYPE_ARMORY_PURCHASE_DEBIT = 'ARMORY_PURCHASE_DEBIT';

    public const string TYPE_ARMORY_REVENUE_CREDIT = 'ARMORY_REVENUE_CREDIT';

    public const string TYPE_PREDICTION_STAKE_DEBIT = 'PREDICTION_STAKE_DEBIT';

    public const string TYPE_PREDICTION_STAKE_CREDIT = 'PREDICTION_STAKE_CREDIT';

    public const string TYPE_PREDICTION_PAYOUT_CREDIT = 'PREDICTION_PAYOUT_CREDIT';

    public const string TYPE_CLAN_LICENSE_DEBIT = 'CLAN_LICENSE_DEBIT';

    public const string TYPE_CLAN_LICENSE_CREDIT = 'CLAN_LICENSE_CREDIT';

    public const string TYPE_DISPUTE_BOND_DEBIT = 'DISPUTE_BOND_DEBIT';

    public const string TYPE_DISPUTE_BOND_CREDIT = 'DISPUTE_BOND_CREDIT';

    public const string TYPE_DISPUTE_FORFEIT_CREDIT = 'DISPUTE_FORFEIT_CREDIT';

    protected static function booted(): void
    {
        static::creating(function (LedgerEntry $entry): void {
            if ($entry->created_at === null) {
                $entry->created_at = now();
            }

            $debit = (int) $entry->debit_cents;
            $credit = (int) $entry->credit_cents;
            $before = (int) $entry->balance_before_cents;
            $after = (int) $entry->balance_after_cents;

            if ($debit < 0 || $credit < 0 || $after < 0) {
                throw new DomainException('Ledger entry amounts and resulting balance must be non-negative.');
            }

            if ($before + $credit - $debit !== $after) {
                throw new DomainException('Ledger entry mathematical conservation invariant violated.');
            }
        });

        static::updating(function (): never {
            throw new DomainException('Immutable ledger: entries cannot be updated or deleted.');
        });

        static::deleting(function (): never {
            throw new DomainException('Immutable ledger: entries cannot be updated or deleted.');
        });
    }

    /**
     * Explicitly block deletion on the Eloquent model instance.
     */
    public function delete(): ?bool
    {
        throw new DomainException('Immutable ledger: entries cannot be updated or deleted.');
    }

    /**
     * Explicitly block force deletion on the Eloquent model instance.
     */
    public function forceDelete(): ?bool
    {
        throw new DomainException('Immutable ledger: entries cannot be updated or deleted.');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'tournament_id' => 'integer',
            'actor_id' => 'integer',
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
            'balance_before_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
