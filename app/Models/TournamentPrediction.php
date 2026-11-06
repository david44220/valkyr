<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'tournament_predictions')]
#[Fillable([
    'tournament_id',
    'user_id',
    'predicted_winner_user_id',
    'stake_cents',
    'payout_cents',
    'status',
    'idempotency_key',
])]
class TournamentPrediction extends Model
{
    public const string STATUS_LOCKED = 'LOCKED';

    public const string STATUS_WON = 'WON';

    public const string STATUS_LOST = 'LOST';

    public const string STATUS_REFUNDED = 'REFUNDED';

    public const int PREDICTION_RAKE_BPS = 1000; // 10.00%

    protected function casts(): array
    {
        return [
            'tournament_id' => 'integer',
            'user_id' => 'integer',
            'predicted_winner_user_id' => 'integer',
            'stake_cents' => 'integer',
            'payout_cents' => 'integer',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function predictedWinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'predicted_winner_user_id');
    }
}
