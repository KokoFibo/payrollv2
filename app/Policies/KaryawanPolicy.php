<?php

namespace App\Policies;

use App\Models\Karyawan;
use App\Models\User;

class KaryawanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrHigher($user);
    }

    public function view(User $user, Karyawan $karyawan): bool
    {
        return $this->isAdminOrHigher($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrHigher($user);
    }

    public function update(User $user, Karyawan $karyawan): bool
    {
        return $this->isAdminOrHigher($user);
    }

    public function delete(User $user, Karyawan $karyawan): bool
    {
        return $this->isAdminOrHigher($user);
    }

    private function isAdminOrHigher(User $user): bool
    {
        return $user->role !== null && $user->role >= 5;
    }
}
