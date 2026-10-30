<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'tournament_registrations')]
#[Fillable([
    'tournament_id',
    'user_id',
    'loadout_class',
    'paid_entry_fee_cents',
    'prize_won_cents',
    'status',
    'idempotency_key',
])]
class TournamentRegistration extends Model
{
    public const string STATUS_REGISTERED = 'REGISTERED';

    public const string STATUS_WINNER_1ST = 'WINNER_1ST';

    public const string STATUS_WINNER_2ND = 'WINNER_2ND';

    public const string STATUS_WINNER_3RD = 'WINNER_3RD';

    public const string STATUS_ELIMINATED = 'ELIMINATED';

    public const string STATUS_REFUNDED = 'REFUNDED';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tournament_id' => 'integer',
            'user_id' => 'integer',
            'paid_entry_fee_cents' => 'integer',
            'prize_won_cents' => 'integer',
        ];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
