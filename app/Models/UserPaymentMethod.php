<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'user_payment_methods')]
#[Fillable([
    'user_id',
    'payment_rail_id',
    'label',
    'account_identifier',
    'is_default',
    'is_verified',
])]
class UserPaymentMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'payment_rail_id' => 'integer',
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
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
     * @return BelongsTo<PaymentRail, $this>
     */
    public function paymentRail(): BelongsTo
    {
        return $this->belongsTo(PaymentRail::class, 'payment_rail_id');
    }
}
