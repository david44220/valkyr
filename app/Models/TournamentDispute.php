<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'tournament_disputes')]
#[Fillable([
    'tournament_id',
    'challenger_user_id',
    'bond_cents',
    'reason',
    'evidence_url',
    'status',
    'resolved_by_admin_id',
    'admin_resolution_notes',
    'idempotency_key',
])]
class TournamentDispute extends Model
{
    public const string STATUS_OPEN = 'OPEN';

    public const string STATUS_UPHELD_REFUNDED = 'UPHELD_REFUNDED';

    public const string STATUS_REJECTED_FORFEITED = 'REJECTED_FORFEITED';

    public const int DEFAULT_BOND_CENTS = 500; // 5,00 €

    protected function casts(): array
    {
        return [
            'tournament_id' => 'integer',
            'challenger_user_id' => 'integer',
            'bond_cents' => 'integer',
            'resolved_by_admin_id' => 'integer',
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
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
