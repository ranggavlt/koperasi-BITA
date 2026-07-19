<?php

namespace App\Policies;

use App\Models\Anggota;
use App\Models\User;

class AnggotaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Anggota $anggota): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Anggota $anggota): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Anggota $anggota): bool
    {
        return $user->role === 'admin';
    }
}
