<?php

namespace App\Http\Middleware;

use App\Models\Karyawan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if (! ($user->is_active ?? true) || $this->isInactiveEmployeeAccount($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Akun Anda sedang nonaktif. Hubungi Finance.');
        }

        return $next($request);
    }

    private function isInactiveEmployeeAccount(object $user): bool
    {
        if (($user->role ?? null) !== 'karyawan') {
            return false;
        }

        if (! $user->karyawan_id) {
            return true;
        }

        $karyawan = $user->relationLoaded('karyawan')
            ? $user->karyawan
            : $user->karyawan()->first();

        return ! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF;
    }
}
