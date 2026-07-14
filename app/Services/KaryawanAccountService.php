<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class KaryawanAccountService
{
    public function createAccount(Karyawan $karyawan, string $temporaryPassword, ?int $financeUserId = null): User
    {
        return DB::transaction(function () use ($karyawan, $temporaryPassword, $financeUserId): User {
            $locked = Karyawan::query()->lockForUpdate()->findOrFail($karyawan->id);

            if ($locked->status_kerja !== Karyawan::STATUS_AKTIF) {
                throw ValidationException::withMessages([
                    'karyawan' => 'Akun hanya dapat dibuat untuk Karyawan aktif.',
                ]);
            }

            if ($locked->user()->exists()) {
                throw ValidationException::withMessages([
                    'karyawan' => 'Karyawan ini sudah mempunyai akun.',
                ]);
            }

            if (User::query()->where('email', $locked->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Email Karyawan sudah dipakai oleh akun lain. Jangan mengambil alih akun secara otomatis.',
                ]);
            }

            return User::query()->create([
                'name' => $locked->nama,
                'email' => $locked->email,
                'password' => Hash::make($temporaryPassword),
                'role' => 'karyawan',
                'karyawan_id' => $locked->id,
                'is_active' => true,
                'must_change_password' => true,
                'password_changed_at' => null,
                'account_created_by' => $financeUserId,
                'account_updated_by' => $financeUserId,
                'account_deactivated_by' => null,
                'account_deactivated_at' => null,
            ]);
        });
    }

    public function activateAccount(Karyawan $karyawan, ?int $financeUserId = null): User
    {
        return DB::transaction(function () use ($karyawan, $financeUserId): User {
            $locked = Karyawan::query()->with('user')->lockForUpdate()->findOrFail($karyawan->id);

            if ($locked->status_kerja !== Karyawan::STATUS_AKTIF) {
                throw ValidationException::withMessages([
                    'karyawan' => 'Akun tidak dapat diaktifkan karena Karyawan sudah berhenti.',
                ]);
            }

            $user = $locked->user;
            if (! $user) {
                throw ValidationException::withMessages([
                    'karyawan' => 'Karyawan ini belum mempunyai akun.',
                ]);
            }

            $user->update([
                'is_active' => true,
                'account_updated_by' => $financeUserId,
                'account_deactivated_by' => null,
                'account_deactivated_at' => null,
            ]);

            return $user->fresh('karyawan');
        });
    }

    public function deactivateAccount(Karyawan $karyawan, ?int $financeUserId = null): ?User
    {
        return DB::transaction(function () use ($karyawan, $financeUserId): ?User {
            $locked = Karyawan::query()->with('user')->lockForUpdate()->findOrFail($karyawan->id);
            $user = $locked->user;

            if (! $user) {
                return null;
            }

            if (! $user->is_active) {
                return $user->fresh('karyawan');
            }

            $user->update([
                'is_active' => false,
                'account_updated_by' => $financeUserId,
                'account_deactivated_by' => $financeUserId,
                'account_deactivated_at' => now(),
            ]);

            return $user->fresh('karyawan');
        });
    }

    public function resetPassword(Karyawan $karyawan, string $temporaryPassword, ?int $financeUserId = null): User
    {
        return DB::transaction(function () use ($karyawan, $temporaryPassword, $financeUserId): User {
            $locked = Karyawan::query()->with('user')->lockForUpdate()->findOrFail($karyawan->id);
            $user = $locked->user;

            if (! $user) {
                throw ValidationException::withMessages([
                    'karyawan' => 'Karyawan ini belum mempunyai akun.',
                ]);
            }

            $user->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
                'account_updated_by' => $financeUserId,
            ]);

            return $user->fresh('karyawan');
        });
    }

    public function changeOwnPassword(User $user, string $password): User
    {
        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'account_updated_by' => $user->id,
        ]);

        return $user->fresh('karyawan');
    }
}
