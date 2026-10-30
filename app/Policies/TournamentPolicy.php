<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

final class TournamentPolicy
{
    /**
     * Anyone can view tournaments.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view a specific tournament.
     */
    public function view(?User $user, Tournament $tournament): bool
    {
        return true;
    }

    /**
     * Only ADMIN or ORGANIZER can create a new tournament.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOrganizer();
    }

    /**
     * Only ADMIN or the owning ORGANIZER of the tournament can settle it and distribute prizes.
     */
    public function settle(User $user, Tournament $tournament): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isOrganizer() && (int) $tournament->organizer_id === (int) $user->id;
    }

    /**
     * Only ADMIN or the owning ORGANIZER of the tournament can cancel it and refund escrow.
     */
    public function cancel(User $user, Tournament $tournament): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isOrganizer() && (int) $tournament->organizer_id === (int) $user->id;
    }
}
