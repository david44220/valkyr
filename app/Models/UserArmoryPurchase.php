<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'user_armory_purchases')]
#[Fillable([
    'user_id',
    'armory_item_id',
    'paid_cents',
    'idempotency_key',
])]
class UserArmoryPurchase extends Model
{
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'armory_item_id' => 'integer',
            'paid_cents' => 'integer',
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
     * @return BelongsTo<ArmoryItem, $this>
     */
    public function armoryItem(): BelongsTo
    {
        return $this->belongsTo(ArmoryItem::class, 'armory_item_id');
    }
}
