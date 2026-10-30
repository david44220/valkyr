<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

final class WalletPolicy
{
    /**
     * Determine whether the user can view the wallet and its ledger entries.
     */
    public function view(User $user, Wallet $wallet): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) $wallet->user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can deposit funds into the wallet.
     */
    public function deposit(User $user, Wallet $wallet): bool
    {
        return (int) $wallet->user_id === (int) $user->id;
    }
}
