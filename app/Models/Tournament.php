<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\TournamentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'tournaments')]
#[UsePolicy(TournamentPolicy::class)]
#[Fillable([
    'organizer_id',
    'slug',
    'title',
    'game',
    'format',
    'status',
    'entry_fee_cents',
    'escrow_pool_cents',
    'sponsored_guarantee_cents',
    'rake_bps',
    'platform_rake_cents',
    'net_prize_pool_cents',
    'max_participants',
    'registered_count',
    'first_place_user_id',
    'second_place_user_id',
    'third_place_user_id',
    'settlement_idempotency_key',
    'starts_at',
    'settled_at',
])]
class Tournament extends Model
{
    public const string STATUS_OPEN = 'OPEN';

    public const string STATUS_LOCKED = 'LOCKED';

    public const string STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const string STATUS_SETTLED = 'SETTLED';

    public const string STATUS_CANCELLED = 'CANCELLED';

    public const int DEFAULT_RAKE_BPS = 800; // 8.00%

    public const array ALLOWED_GAMES = [
        'VALORANT // PROTOCOL',
        'COUNTER-STRIKE 2',
        'APEX // SOVEREIGN',
        'LEAGUE OF LEGENDS',
    ];

    public const array ALLOWED_FORMATS = [
        '1v1',
        '5v5',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organizer_id' => 'integer',
            'entry_fee_cents' => 'integer',
            'escrow_pool_cents' => 'integer',
            'sponsored_guarantee_cents' => 'integer',
            'rake_bps' => 'integer',
            'platform_rake_cents' => 'integer',
            'net_prize_pool_cents' => 'integer',
            'max_participants' => 'integer',
            'registered_count' => 'integer',
            'first_place_user_id' => 'integer',
            'second_place_user_id' => 'integer',
            'third_place_user_id' => 'integer',
            'starts_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * @return HasMany<TournamentRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class, 'tournament_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function firstPlace(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_place_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function secondPlace(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_place_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function thirdPlace(): BelongsTo
    {
        return $this->belongsTo(User::class, 'third_place_user_id');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'tournament_id')->orderByDesc('id');
    }

    /**
     * @param  Builder<Tournament>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    public function projectedGrossPoolCents(): int
    {
        $currentPool = (int) $this->escrow_pool_cents;
        $maxProjected = (int) $this->entry_fee_cents * (int) $this->max_participants;

        return max($currentPool, $maxProjected) + (int) $this->sponsored_guarantee_cents;
    }

    public function currentNetPoolCents(): int
    {
        if ($this->status === self::STATUS_SETTLED && (int) $this->net_prize_pool_cents > 0) {
            return (int) $this->net_prize_pool_cents;
        }

        $gross = (int) $this->escrow_pool_cents;
        $rake = intdiv($gross * (int) ($this->rake_bps ?: self::DEFAULT_RAKE_BPS), 10000);

        return $gross - $rake;
    }
}
