<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function update(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }
}
