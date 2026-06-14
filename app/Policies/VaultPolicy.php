<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vault;

class VaultPolicy
{
    public function view(User $user, Vault $vault): bool
    {
        return $user->id === $vault->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vault $vault): bool
    {
        return $user->id === $vault->user_id;
    }

    public function delete(User $user, Vault $vault): bool
    {
        return $user->id === $vault->user_id;
    }
}