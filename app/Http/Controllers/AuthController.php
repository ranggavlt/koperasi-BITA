<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Karyawan;
use App\Services\KaryawanAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        return redirect()->route('pages.dashboard');
    }

    public function showRegister()
    {
        return view('pages.sign-up');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'kode_keuangan' => ['nullable', 'string', 'max:255'],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $role = 'kasir';
        $kodeKeuangan = trim((string) ($validated['kode_keuangan'] ?? ''));

        if ($kodeKeuangan !== '') {
            $expected = (string) config('koperasi.keuangan_register_code', '');
            if ($expected === '' || ! hash_equals($expected, $kodeKeuangan)) {
                throw ValidationException::withMessages([
                    'kode_keuangan' => 'Kode khusus Keuangan tidak valid.',
                ]);
            }
            $role = 'keuangan';
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return redirect()
            ->route('login')
            ->with('success', 'Registrasi berhasil. Silakan login.');
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
