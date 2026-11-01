<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'wallet_funding_requests')]
#[Fillable([
    'user_id',
    'wallet_id',
    'payment_rail_id',
    'user_payment_method_id',
    'direction',
    'gross_amount_cents',
    'fee_cents',
    'net_amount_cents',
    'reference_code',
    'status',
    'idempotency_key',
    'processed_by_admin_id',
    'admin_notes',
    'processed_at',
])]
class WalletFundingRequest extends Model
{
    public const string DIRECTION_DEPOSIT = 'DEPOSIT';

    public const string DIRECTION_WITHDRAWAL = 'WITHDRAWAL';

    public const string STATUS_PENDING_ADMIN = 'PENDING_ADMIN';

    public const string STATUS_SETTLED = 'SETTLED';

    public const string STATUS_REJECTED = 'REJECTED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'wallet_id' => 'integer',
            'payment_rail_id' => 'integer',
            'user_payment_method_id' => 'integer',
            'gross_amount_cents' => 'integer',
            'fee_cents' => 'integer',
            'net_amount_cents' => 'integer',
            'processed_by_admin_id' => 'integer',
            'processed_at' => 'immutable_datetime',
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
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    /**
     * @return BelongsTo<PaymentRail, $this>
     */
    public function paymentRail(): BelongsTo
    {
        return $this->belongsTo(PaymentRail::class, 'payment_rail_id');
    }

    /**
     * @return BelongsTo<UserPaymentMethod, $this>
     */
    public function userPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(UserPaymentMethod::class, 'user_payment_method_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_admin_id');
    }
}
