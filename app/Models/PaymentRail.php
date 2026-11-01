<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'payment_rails')]
#[Fillable([
    'code',
    'name',
    'provider',
    'method_type',
    'supports_deposit',
    'supports_withdrawal',
    'requires_admin_approval',
    'is_active',
    'deposit_fee_bps',
    'deposit_fixed_fee_cents',
    'withdrawal_fee_bps',
    'withdrawal_fixed_fee_cents',
    'min_amount_cents',
    'max_amount_cents',
    'account_instructions',
    'settlement_delay_label',
])]
class PaymentRail extends Model
{
    public const string TYPE_BANK_TRANSFER = 'BANK_TRANSFER';

    public const string TYPE_CRYPTO = 'CRYPTO';

    public const string TYPE_CARD = 'CARD';

    public const string TYPE_MANUAL_VOUCHER = 'MANUAL_VOUCHER';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports_deposit' => 'boolean',
            'supports_withdrawal' => 'boolean',
            'requires_admin_approval' => 'boolean',
            'is_active' => 'boolean',
            'deposit_fee_bps' => 'integer',
            'deposit_fixed_fee_cents' => 'integer',
            'withdrawal_fee_bps' => 'integer',
            'withdrawal_fixed_fee_cents' => 'integer',
            'min_amount_cents' => 'integer',
            'max_amount_cents' => 'integer',
        ];
    }

    /**
     * @return HasMany<UserPaymentMethod, $this>
     */
    public function userPaymentMethods(): HasMany
    {
        return $this->hasMany(UserPaymentMethod::class, 'payment_rail_id');
    }

    /**
     * @return HasMany<WalletFundingRequest, $this>
     */
    public function fundingRequests(): HasMany
    {
        return $this->hasMany(WalletFundingRequest::class, 'payment_rail_id');
    }

    public function calculateDepositFeeCents(int $grossAmountCents, bool $isPrime = false): int
    {
        if ($isPrime || $grossAmountCents <= 0) {
            return 0;
        }

        $variableFee = intdiv($grossAmountCents * max(0, (int) $this->deposit_fee_bps), 10000);
        $fixedFee = max(0, (int) $this->deposit_fixed_fee_cents);

        return $variableFee + $fixedFee;
    }

    public function calculateWithdrawalFeeCents(int $grossAmountCents, bool $isPrime = false): int
    {
        if ($isPrime || $grossAmountCents <= 0) {
            return 0;
        }

        $variableFee = intdiv($grossAmountCents * max(0, (int) $this->withdrawal_fee_bps), 10000);
        $fixedFee = max(0, (int) $this->withdrawal_fixed_fee_cents);

        return $variableFee + $fixedFee;
    }
}
