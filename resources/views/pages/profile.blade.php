@extends('layout.main')
@section('content')
@php
    $user = auth()->user();
    $karyawan = $user->karyawan;
@endphp

<div class="w-full px-6 mx-auto">
  <div
    class="relative flex items-center p-0 mt-6 overflow-hidden bg-center bg-cover min-h-75 rounded-2xl"
    style="
      background-image: url('{{ asset('assets/img/curved-images/curved0.jpg') }}');
      background-position-y: 50%;
    ">
    <span
      class="absolute inset-y-0 w-full h-full bg-center bg-cover bg-gradient-to-tl from-blue-900 to-cyan-700 opacity-60"></span>
  </div>
  
  <div
    class="relative flex flex-col flex-auto min-w-0 p-4 mx-6 -mt-16 overflow-hidden break-words border-0 shadow-blur rounded-2xl bg-white/80 bg-clip-border backdrop-blur-2xl backdrop-saturate-200">
    <div class="flex flex-wrap -mx-3">
      <div class="flex-none w-auto max-w-full px-3">
        <div
          class="text-base ease-soft-in-out h-20 w-20 relative inline-flex items-center justify-center rounded-xl text-white transition-all duration-200 shadow-soft-sm bg-white border border-white">
          @if($user->avatar_path)
            <img
              src="{{ Storage::url($user->avatar_path) }}"
              alt="profile_image"
              class="w-full h-full object-cover rounded-xl" />
          @else
            <div class="w-full h-full bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 text-3xl">
              <i class="fas fa-user"></i>
            </div>
          @endif
        </div>
      </div>
      <div class="flex-none w-auto max-w-full px-3 my-auto">
        <div class="h-full">
          <h5 class="mb-1 text-slate-700 font-bold text-lg">{{ $karyawan->nama ?? $user->name }}</h5>
          <p class="mb-0 font-semibold leading-normal text-sm text-slate-500 uppercase tracking-widest text-xs">
            Role: {{ $user->role }}
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="w-full p-6 mx-auto">
  <div class="flex justify-center mt-2">
    <div class="w-full max-w-2xl">
      <div
        class="relative flex flex-col h-full min-w-0 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-4 pb-0 mb-0 bg-white border-b-0 rounded-t-2xl">
          <div class="flex flex-wrap -mx-3">
            <div
              class="flex items-center w-full max-w-full px-3 shrink-0">
              <h6 class="mb-0 font-bold text-slate-700 text-base">Informasi Akun Login</h6>
            </div>
          </div>
        </div>
        <div class="flex-auto p-4">
          <ul class="flex flex-col pl-0 mb-0 rounded-lg">
            <li
              class="relative block px-4 py-3 pt-0 pl-0 leading-normal bg-white border-0 border-b border-slate-100 text-sm text-inherit">
              <span class="text-slate-400 text-xs uppercase font-bold tracking-widest block mb-1">Username Login</span>
              <span class="font-bold text-slate-700">{{ $user->name }}</span>
            </li>
            <li
              class="relative block px-4 py-3 pl-0 leading-normal bg-white border-0 border-b border-slate-100 text-sm text-inherit">
              <span class="text-slate-400 text-xs uppercase font-bold tracking-widest block mb-1">Email System</span>
              <span class="font-bold text-slate-700">{{ $user->email }}</span>
            </li>
            <li
              class="relative block px-4 py-3 pb-0 pl-0 leading-normal bg-white border-0 text-sm text-inherit">
              <span class="text-slate-400 text-xs uppercase font-bold tracking-widest block mb-1">Status Akun</span>
              <span class="inline-flex items-center px-2 py-1 text-xs font-bold text-green-700 bg-green-100 rounded-lg uppercase">Aktif</span>
            </li>
          </ul>
          
          <div class="mt-6 pt-6 border-t border-slate-100 text-center">
            <p class="text-sm text-slate-400">Hubungi Administrator jika ingin mengubah informasi di atas.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
