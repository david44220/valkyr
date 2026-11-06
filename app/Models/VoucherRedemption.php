<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'voucher_redemptions')]
#[Fillable([
    'voucher_code_id',
    'user_id',
    'credited_cents',
    'idempotency_key',
])]
class VoucherRedemption extends Model
{
    protected function casts(): array
    {
        return [
            'voucher_code_id' => 'integer',
            'user_id' => 'integer',
            'credited_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VoucherCode, $this>
     */
    public function voucherCode(): BelongsTo
    {
        return $this->belongsTo(VoucherCode::class, 'voucher_code_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
