@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Master Data</p>
      <h1 class="text-2xl font-bold text-slate-700">Manajemen User</h1>
      <p class="mt-1 text-sm text-slate-400">Pusat pengaturan hak akses login (Admin, Kasir, Karyawan).</p>
    </div>
    <button type="button" onclick="toggleMasterForm()" id="btn-toggle-form"
      class="kbsm-btn kbsm-btn--navy">
      + Buat Akun Baru
    </button>
  </div>

  <section id="form-container" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl hidden">
    <div class="mb-5">
      <h2 class="text-base font-bold text-slate-700 m-0">Form Akun Baru</h2>
      <p class="text-sm text-slate-400">Buat akun untuk memberi hak akses ke dalam sistem.</p>
    </div>

    <form action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="name">Nama Lengkap</label>
          <input id="name" name="name" type="text" value="{{ old('name') }}" required
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="email">Email Login</label>
          <input id="email" name="email" type="email" value="{{ old('email') }}" required
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="role">Hak Akses (Role)</label>
          <select id="role" name="role" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="kasir" {{ old('role') === 'kasir' ? 'selected' : '' }}>Kasir</option>
            <option value="karyawan" {{ old('role') === 'karyawan' ? 'selected' : '' }}>Karyawan</option>
            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="password">Password Sementara</label>
          <input id="password" name="password" type="password" minlength="8" required placeholder="Minimal 8 karakter"
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="avatar">Foto Profil (Avatar)</label>
          <input id="avatar" name="avatar" type="file" accept="image/*"
            class="w-full rounded-xl border border-slate-200 px-4 py-2 text-sm bg-white file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
        </div>
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="karyawan_id">Hubungkan ke Profil Karyawan (Opsional)</label>
          <select id="karyawan_id" name="karyawan_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="">-- Tidak Terhubung ke Karyawan --</option>
            @foreach($karyawans as $kry)
              <option value="{{ $kry->id }}" {{ old('karyawan_id') == $kry->id ? 'selected' : '' }}>
                {{ $kry->nama }} ({{ $kry->jabatan }})
              </option>
            @endforeach
          </select>
          <p class="mt-1 text-xs text-slate-400">Pilih karyawan jika akun ini butuh mengakses layanan khusus karyawan (seperti pengajuan sewa mobil).</p>
        </div>
      </div>
      <div class="mt-6 flex gap-3">
        <button class="rounded-xl px-6 py-3 text-xs font-bold uppercase text-white shadow-lg" style="background-color: #2f8f3a;" onmouseover="this.style.backgroundColor='#267832'" onmouseout="this.style.backgroundColor='#2f8f3a'" type="submit">
          Simpan Akun
        </button>
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h2 class="text-base font-bold text-slate-700 m-0">Daftar Akun Pengguna</h2>
      <p class="text-sm text-slate-400">Kelola semua hak akses login sistem.</p>
    </div>
    <div style="overflow-x: auto;">
      <table class="w-full min-w-[1000px] text-left text-sm">
        <thead class="bg-[#073b5c] text-xs uppercase text-white">
          <tr>
            <th class="px-6 py-4">Nama</th>
            <th class="px-6 py-4">Email</th>
            <th class="px-6 py-4">Role</th>
            <th class="px-6 py-4">Status / Profil Karyawan</th>
            <th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($users as $user)
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-semibold text-slate-700">{{ $user->name }}</td>
              <td class="px-6 py-4 text-slate-600">{{ $user->email }}</td>
              <td class="px-6 py-4">
                @if($user->role === 'admin')
                  <span class="rounded-md bg-purple-100 px-2 py-1 text-xs font-bold text-purple-700">Admin</span>
                @elseif($user->role === 'kasir')
                  <span class="rounded-md bg-blue-100 px-2 py-1 text-xs font-bold text-blue-700">Kasir</span>
                @else
                  <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">Karyawan</span>
                @endif
              </td>
              <td class="px-6 py-4">
                <div class="mb-1">
                  @if($user->is_active)
                    <span class="rounded-md bg-green-100 px-2 py-1 text-xs font-bold text-green-700">Aktif</span>
                  @else
                    <span class="rounded-md bg-red-100 px-2 py-1 text-xs font-bold text-red-700">Nonaktif</span>
                  @endif
                </div>
                <div class="text-xs text-slate-500">
                  @if($user->karyawan)
                    Terhubung: {{ $user->karyawan->nama }}
                  @else
                    <span class="text-slate-400 italic">Tidak terhubung profil</span>
                  @endif
                </div>
              </td>
              <td class="px-6 py-4 text-center">
                <div class="flex flex-wrap items-center justify-center gap-2">
                  <button type="button" onclick="openEditModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}', '{{ $user->role }}', '{{ $user->karyawan_id }}')" class="rounded-lg px-3 py-2 text-xs font-bold text-white" style="background-color: #073b5c;">Edit</button>
                  <button type="button" onclick="openResetModal({{ $user->id }}, '{{ addslashes($user->name) }}')" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600">Reset Sandi</button>
                  <form action="{{ route('users.toggle-status', $user) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin mengubah status akun ini?');">
                    @csrf @method('PATCH')
                    <button type="submit" class="rounded-lg border {{ $user->is_active ? 'border-amber-300 text-amber-700' : 'border-[#2f8f3a] text-[#2f8f3a]' }} px-3 py-2 text-xs font-bold">
                      {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada data akun.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $users->links() }}</div>
  </section>
</div>

{{-- MODAL EDIT --}}
<div id="edit-modal" class="fixed inset-0 z-50 flex hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm">
  <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl mx-4">
    <h3 class="mb-4 text-lg font-bold text-slate-700">Edit Akun</h3>
    <form id="edit-form" method="POST" enctype="multipart/form-data">
      @csrf @method('PUT')
      <div class="grid gap-4 mb-6">
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Nama Lengkap</label>
          <input id="edit_name" name="name" type="text" required class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Email Login</label>
          <input id="edit_email" name="email" type="email" required class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Hak Akses (Role)</label>
          <select id="edit_role" name="role" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option value="kasir">Kasir</option>
            <option value="karyawan">Karyawan</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Hubungkan Karyawan</label>
          <select id="edit_karyawan_id" name="karyawan_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option value="">-- Tidak Terhubung --</option>
            @foreach($karyawans as $kry)
              <option value="{{ $kry->id }}">{{ $kry->nama }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Ganti Foto Profil (Opsional)</label>
          <input id="edit_avatar" name="avatar" type="file" accept="image/*"
            class="w-full rounded-xl border border-slate-200 px-4 py-2 text-sm bg-white file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
        </div>
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeEditModal()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-500">Batal</button>
        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold text-white" style="background-color: #073b5c;">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

{{-- MODAL RESET SANDI --}}
<div id="reset-modal" class="fixed inset-0 z-50 flex hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm">
  <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl mx-4">
    <h3 class="mb-4 text-lg font-bold text-slate-700">Reset Sandi</h3>
    <p class="mb-4 text-sm text-slate-500">Reset sandi untuk <strong id="reset_user_name"></strong></p>
    <form id="reset-form" method="POST">
      @csrf @method('PATCH')
      <div class="mb-6">
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Password Sementara Baru</label>
        <input name="new_password" type="text" minlength="8" required placeholder="Minimal 8 karakter" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-2 text-sm" />
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeResetModal()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-500">Batal</button>
        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold text-white" style="background-color: #2f8f3a;">Reset Sandi</button>
      </div>
    </form>
  </div>
</div>

<script>
  function toggleMasterForm() {
    const panel = document.getElementById('form-container');
    const button = document.getElementById('btn-toggle-form');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Buat Akun Baru' : 'Tutup Form';
  }

  function openEditModal(id, name, email, role, karyawan_id) {
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_karyawan_id').value = karyawan_id || '';
    document.getElementById('edit-form').action = `/users/${id}`;
    document.getElementById('edit-modal').classList.remove('hidden');
  }

  function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
  }

  function openResetModal(id, name) {
    document.getElementById('reset_user_name').innerText = name;
    document.getElementById('reset-form').action = `/users/${id}/reset-password`;
    document.getElementById('reset-modal').classList.remove('hidden');
  }

  function closeResetModal() {
    document.getElementById('reset-modal').classList.add('hidden');
  }
</script>
@endsection
