<?php

namespace App\Policies;

use App\Models\PengurusKoperasi;
use App\Models\User;

class PengurusKoperasiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, PengurusKoperasi $pengurus): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, PengurusKoperasi $pengurus): bool
    {
        return $user->role === 'admin';
    }
}
