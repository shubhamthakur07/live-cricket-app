<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CricketMatch as MatchModel;

class MatchPolicy
{
    public function update(User $user, MatchModel $match): bool
    {
        return $user->id === $match->user_id;
    }

    public function delete(User $user, MatchModel $match): bool
    {
        return $user->id === $match->user_id;
    }
}