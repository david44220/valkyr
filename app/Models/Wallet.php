<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\WalletPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'wallets')]
#[UsePolicy(WalletPolicy::class)]
#[Fillable([
    'user_id',
    'balance_cents',
    'locked_escrow_cents',
    'lifetime_won_cents',
    'currency',
])]
class Wallet extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'balance_cents' => 'integer',
            'locked_escrow_cents' => 'integer',
            'lifetime_won_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'wallet_id')->orderByDesc('id');
    }

    public function formattedBalance(): string
    {
        return self::formatCents((int) $this->balance_cents);
    }

    public function formattedLockedEscrow(): string
    {
        return self::formatCents((int) $this->locked_escrow_cents);
    }

    public function formattedLifetimeWon(): string
    {
        return self::formatCents((int) $this->lifetime_won_cents);
    }

    public static function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $euros = intdiv($abs, 100);
        $rem = $abs % 100;

        return sprintf('%s%s,%02d €', $sign, number_format($euros, 0, ',', ' '), $rem);
    }
}
