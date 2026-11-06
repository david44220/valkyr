<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Table(name: 'users')]
#[Fillable([
    'name',
    'gamertag',
    'email',
    'password',
    'role',
    'elo_rating',
    'preferred_class',
    'equipped_badge',
    'clan_tag',
    'is_prime',
    'prime_expires_at',
    'is_frozen',
    'kyc_status',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const string ROLE_ADMIN = 'ADMIN';

    public const string ROLE_ORGANIZER = 'ORGANIZER';

    public const string ROLE_PLAYER = 'PLAYER';

    public const string KYC_STANDARD = 'STANDARD';

    public const string KYC_VERIFIED_PRO = 'VERIFIED_PRO';

    public const string KYC_INSTITUTIONAL = 'INSTITUTIONAL';

    public const array ALLOWED_CLASSES = [
        'VANGUARD',
        'SPECTRE',
        'CHRONO',
        'VALKYRIE',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'elo_rating' => 'integer',
            'is_prime' => 'boolean',
            'prime_expires_at' => 'immutable_datetime',
            'is_frozen' => 'boolean',
        ];
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    /**
     * @return HasMany<Tournament, $this>
     */
    public function organizedTournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'organizer_id');
    }

    /**
     * @return HasMany<TournamentRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class, 'user_id');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function triggeredLedgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'actor_id');
    }

    /**
     * @return HasMany<UserPaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(UserPaymentMethod::class, 'user_id')->orderByDesc('is_default')->orderByDesc('id');
    }

    /**
     * @return HasMany<WalletFundingRequest, $this>
     */
    public function fundingRequests(): HasMany
    {
        return $this->hasMany(WalletFundingRequest::class, 'user_id')->orderByDesc('id');
    }

    /**
     * @return HasMany<UserArmoryPurchase, $this>
     */
    public function armoryPurchases(): HasMany
    {
        return $this->hasMany(UserArmoryPurchase::class, 'user_id')->orderByDesc('id');
    }

    /**
     * @return HasMany<TournamentPrediction, $this>
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(TournamentPrediction::class, 'user_id')->orderByDesc('id');
    }

    /**
     * @return HasMany<TournamentDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(TournamentDispute::class, 'challenger_user_id')->orderByDesc('id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    public function isPlayer(): bool
    {
        return $this->role === self::ROLE_PLAYER;
    }
}
