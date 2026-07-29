<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Karyawan;
use App\Services\KaryawanAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('pages.sign-in');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if (! (Auth::user()->is_active ?? true) || $this->isInactiveEmployeeAccount(Auth::user())) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda sedang nonaktif. Hubungi Finance.',
            ]);
        }

        $request->session()->regenerate();

        if (Auth::user()->must_change_password ?? false) {
            return redirect()->route('password.change');
        }

        return match (Auth::user()->role) {
            'kasir' => redirect()->route('waserba.index'),
            default => redirect()->route('pages.dashboard'),
        };
    }

    private function isInactiveEmployeeAccount(User $user): bool
    {
        if ($user->role !== 'karyawan') {
            return false;
        }

        if (! $user->karyawan_id) {
            return true;
        }

        $karyawan = $user->karyawan()->first();

        return ! $karyawan || $karyawan->status_kerja !== Karyawan::STATUS_AKTIF;
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showChangePassword()
    {
        return view('pages.auth.change-password');
    }

    public function updatePassword(Request $request, KaryawanAccountService $service)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Password sementara tidak sesuai.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ]);

        $service->changeOwnPassword($request->user(), $validated['password']);

        return redirect()
            ->route('pages.dashboard')
            ->with('success', 'Password berhasil diganti.');
    }
}
