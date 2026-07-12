@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <section class="mx-auto max-w-xl rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Keamanan Akun</p>
    <h1 class="text-2xl font-bold text-slate-700">Ganti Password Sementara</h1>
    <p class="mt-1 text-sm text-slate-400">Akun Karyawan wajib mengganti password sementara sebelum memakai aplikasi.</p>

    <form class="mt-6 space-y-4" method="POST" action="{{ route('password.update') }}">
      @csrf
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Password sementara</label>
        <input type="password" name="current_password" required class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Password baru</label>
        <input type="password" name="password" required minlength="8" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Konfirmasi password baru</label>
        <input type="password" name="password_confirmation" required minlength="8" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <button class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832]">Simpan Password</button>
    </form>
  </section>
</div>
@endsection
