<?php

namespace App\Policies;

use App\Models\Exchange;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExchangePolicy
{
    use HandlesAuthorization;

    public function view(User $user, Exchange $exchange)
    {
        return $user->id === $exchange->requester_id || $user->id === $exchange->accepter_id || $user->isAdmin();
    }

    public function update(User $user, Exchange $exchange)
    {
        return $user->id === $exchange->accepter_id || $user->isAdmin();
    }

    public function delete(User $user, Exchange $exchange)
    {
        return $user->id === $exchange->requester_id || $user->isAdmin();
    }
}
