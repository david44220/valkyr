<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'wager_duels')]
#[Fillable([
    'code',
    'challenger_id',
    'opponent_id',
    'winner_id',
    'game',
    'loadout_class',
    'stake_cents',
    'escrow_pot_cents',
    'rake_bps',
    'platform_rake_cents',
    'winner_payout_cents',
    'status',
    'idempotency_key',
    'settled_at',
])]
class WagerDuel extends Model
{
    public const string STATUS_OPEN = 'OPEN';

    public const string STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const string STATUS_SETTLED = 'SETTLED';

    public const string STATUS_CANCELLED = 'CANCELLED';

    public const int DEFAULT_RAKE_BPS = 800;

    protected function casts(): array
    {
        return [
            'challenger_id' => 'integer',
            'opponent_id' => 'integer',
            'winner_id' => 'integer',
            'stake_cents' => 'integer',
            'escrow_pot_cents' => 'integer',
            'rake_bps' => 'integer',
            'platform_rake_cents' => 'integer',
            'winner_payout_cents' => 'integer',
            'settled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
