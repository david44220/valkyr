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

    public const string TYPE_BUY_IN_ESCROW_DEBIT = 'BUY_IN_ESCROW_DEBIT';

    public const string TYPE_BUY_IN_ESCROW_CREDIT = 'BUY_IN_ESCROW_CREDIT';

    public const string TYPE_PRIZE_PAYOUT_1ST = 'PRIZE_PAYOUT_1ST';

    public const string TYPE_PRIZE_PAYOUT_2ND = 'PRIZE_PAYOUT_2ND';

    public const string TYPE_PRIZE_PAYOUT_3RD = 'PRIZE_PAYOUT_3RD';

    public const string TYPE_ESCROW_RELEASE_DEBIT = 'ESCROW_RELEASE_DEBIT';

    public const string TYPE_PLATFORM_RAKE_CREDIT = 'PLATFORM_RAKE_CREDIT';

    public const string TYPE_TOURNAMENT_REFUND_DEBIT = 'TOURNAMENT_REFUND_DEBIT';

    public const string TYPE_TOURNAMENT_REFUND_CREDIT = 'TOURNAMENT_REFUND_CREDIT';

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
