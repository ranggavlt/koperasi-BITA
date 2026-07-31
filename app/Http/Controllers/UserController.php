<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);

        return view('pages.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', 'string', 'in:admin,kasir'],
            'password' => ['required', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;
        $validated['must_change_password'] = true;
        $validated['account_created_by'] = auth()->id();
        $validated['account_updated_by'] = auth()->id();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_path'] = $path;
        }

        User::create($validated);

        return redirect()->route('users.index')
            ->with('success', 'Akun berhasil dibuat. Pengguna wajib mengganti password saat login pertama.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', 'in:admin,kasir'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $validated['account_updated_by'] = auth()->id();

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_path'] = $path;
        }

        $user->update($validated);

        return redirect()->route('users.index')
            ->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        // Prevent deleting oneself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->withErrors(['delete' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Akun pengguna berhasil dihapus.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => true,
            'password_changed_at' => null,
            'account_updated_by' => auth()->id(),
        ]);

        return redirect()->route('users.index')
            ->with('success', 'Password pengguna berhasil direset.');
    }

    public function toggleStatus(User $user)
    {
        // Prevent toggling oneself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->withErrors(['status' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
        }

        $user->update([
            'is_active' => !$user->is_active,
            'account_updated_by' => auth()->id(),
            'account_deactivated_by' => !$user->is_active ? auth()->id() : null,
            'account_deactivated_at' => !$user->is_active ? now() : null,
        ]);

        $statusMessage = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('users.index')
            ->with('success', "Status akun berhasil $statusMessage.");
    }
}
