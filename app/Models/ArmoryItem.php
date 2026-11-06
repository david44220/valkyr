<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'armory_items')]
#[Fillable([
    'sku',
    'name',
    'category',
    'description',
    'price_cents',
    'elo_bonus',
    'badge_label',
    'is_active',
])]
class ArmoryItem extends Model
{
    public const string CATEGORY_BADGE_TITLE = 'BADGE_TITLE';

    public const string CATEGORY_ELO_SHIELD = 'ELO_SHIELD';

    public const string CATEGORY_AVATAR_FRAME = 'AVATAR_FRAME';

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'elo_bonus' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<UserArmoryPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(UserArmoryPurchase::class, 'armory_item_id');
    }
}
