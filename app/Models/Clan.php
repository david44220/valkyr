<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'clans')]
#[Fillable([
    'tag',
    'name',
    'owner_id',
    'revenue_share_bps',
    'member_count',
    'license_paid_cents',
])]
class Clan extends Model
{
    public const int LICENSE_PRICE_CENTS = 2900; // 29,00 €

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'revenue_share_bps' => 'integer',
            'member_count' => 'integer',
            'license_paid_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'clan_tag', 'tag');
    }
}
