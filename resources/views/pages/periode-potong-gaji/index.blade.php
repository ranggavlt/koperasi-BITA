@extends('layout.main')

@section('content')
@php
  use App\Models\LimitPotongGajiAnggota;
  use App\Models\PemakaianPotongGaji;
  use App\Models\Pinjaman;

  $fmt = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $periodeLabel = $selectedPeriode ? $selectedPeriode->periode->locale('id')->translatedFormat('F Y') : '-';
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Payroll & Cicilan</p>
      <h1 class="kbsm-business-title">Periode Potong Gaji</h1>
      <p class="kbsm-business-subtitle">Kelola limit bulanan, reservasi cicilan, penutupan, dan konfirmasi payroll per anggota pada setiap periode bulanan.</p>
    </div>
    <div class="kbsm-business-actions">
      <a href="{{ route('periode-potong-gaji.create') }}" class="kbsm-btn kbsm-btn--navy">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Buka Periode Baru
      </a>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Pilih Periode Aktif</h2>
      <p class="kbsm-business-panel__copy">Pilih bulan periode untuk melihat atau mengelola konfigurasi limit masing-masing karyawan.</p>
    </div>
    <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
      @foreach($periodeList as $periode)
        <a href="{{ route('periode-potong-gaji.index', ['periode_id' => $periode->id]) }}"
          class="px-4 py-2 text-xs font-bold uppercase rounded-lg border {{ $selectedPeriode && $selectedPeriode->id === $periode->id ? 'bg-emerald-600 border-emerald-600 text-white shadow-md' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }} transition-colors">
          {{ $periode->periode->format('M Y') }}
          <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] {{ $selectedPeriode && $selectedPeriode->id === $periode->id ? 'bg-emerald-700' : 'bg-slate-100' }}">{{ $periode->status }}</span>
        </a>
      @endforeach
    </div>
    @if($periodeList->hasPages())
    <div class="p-3 bg-slate-50 border-b border-slate-100">{{ $periodeList->links() }}</div>
    @endif

    @if($selectedPeriode)
      <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-bold text-slate-800 text-lg">Konfigurasi Limit: {{ $periodeLabel }}</h3>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-left text-sm text-slate-600 border-collapse">
            <thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-500">
              <tr>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Anggota</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Konfigurasi Limit</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Pemakaian</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200">Status</th>
                <th class="px-4 py-3 font-semibold border-b border-slate-200 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @foreach($anggotaAktif as $anggota)
                @php
                  $limit = $limits->get($anggota->id);
                  $activePinjaman = $anggota->pinjaman->firstWhere('status', Pinjaman::STATUS_AKTIF);
                  $jadwalPeriode = $activePinjaman?->jadwalCicilan?->first(fn ($jadwal) => $jadwal->periode->toDateString() === $selectedPeriode->periode->toDateString());
                  $reserved = $limit ? $limit->pemakaian->where('status', PemakaianPotongGaji::STATUS_RESERVED)->sum('nominal') : 0;
                  $consumed = $limit ? $limit->pemakaian->where('status', PemakaianPotongGaji::STATUS_CONSUMED)->sum('nominal') : 0;
                  $activeUsed = $reserved + $consumed;
                  $sisa = $limit ? ((float) $limit->limit_nominal - (float) $activeUsed) : null;
                  $canEdit = $limit && in_array($limit->status, [LimitPotongGajiAnggota::STATUS_DRAFT, LimitPotongGajiAnggota::STATUS_ACTIVE], true);
                @endphp
                <tr class="hover:bg-slate-50/50 transition-colors align-top">
                  <td class="px-4 py-4">
                    <p class="mb-0 font-bold text-slate-800">{{ $anggota->nomor_anggota }}</p>
                    <p class="mb-0 text-sm font-medium text-slate-600">{{ $anggota->karyawan->nama ?? '-' }}</p>
                    <p class="mb-0 text-xs text-slate-400 mt-1">{{ $anggota->status }} / {{ $anggota->karyawan->status_kerja ?? '-' }}</p>
                  </td>
                  
                  <td class="px-4 py-4">
                    @if($limit)
                      <form action="{{ route('periode-potong-gaji.limit.update', $limit) }}" method="POST" class="flex flex-col gap-2 max-w-[200px]">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-center gap-2">
                          <input type="number" name="limit_nominal" min="0" value="{{ (int) $limit->limit_nominal }}"
                            class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm bg-white" {{ $canEdit ? '' : 'readonly disabled' }}>
                        </div>
                        @if($canEdit)
                          <input type="text" name="alasan" value="Penyesuaian limit {{ $periodeLabel }}" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs text-slate-500" placeholder="Alasan">
                          <button class="kbsm-btn kbsm-btn--sm kbsm-btn--outline-slate w-full !text-[11px] !py-1">Simpan Limit</button>
                        @endif
                      </form>
                    @else
                      <form action="{{ route('periode-potong-gaji.limit.store') }}" method="POST" class="flex flex-col gap-2 max-w-[200px]">
                        @csrf
                        <input type="hidden" name="periode_id" value="{{ $selectedPeriode->id }}">
                        <input type="hidden" name="anggota_id" value="{{ $anggota->id }}">
                        <input type="number" name="limit_nominal" min="0" placeholder="Nominal Limit" class="w-full rounded border border-emerald-300 bg-emerald-50/30 px-2 py-1.5 text-sm placeholder:text-slate-400">
                        <input type="text" name="alasan" value="Limit awal {{ $periodeLabel }}" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs text-slate-500" placeholder="Alasan">
                        <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald w-full !text-[11px] !py-1">Buat Konfigurasi</button>
                      </form>
                    @endif
                  </td>
                  
                  <td class="px-4 py-4">
                    <div class="flex flex-col gap-1.5 text-xs">
                      <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Cicilan Bln Ini</span>
                        <span class="font-medium text-slate-700">{{ $jadwalPeriode ? $fmt($jadwalPeriode->nominal_pokok) : '-' }}</span>
                      </div>
                      <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Sisa Limit</span>
                        <span class="font-bold text-emerald-600">{{ $limit ? $fmt($sisa) : '-' }}</span>
                      </div>
                      <div class="flex justify-between gap-4 pt-1 border-t border-slate-100">
                        <span class="text-slate-400">Total Reserve</span>
                        <span class="text-slate-600">{{ $fmt($reserved) }}</span>
                      </div>
                      <div class="flex justify-between gap-4">
                        <span class="text-slate-400">Total Consumed</span>
                        <span class="text-slate-600">{{ $fmt($consumed) }}</span>
                      </div>
                    </div>
                  </td>
                  
                  <td class="px-4 py-4">
                    @if($limit)
                      <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                        {{ $limit->status === 'draft' ? 'bg-slate-100 text-slate-600' : '' }}
                        {{ $limit->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                        {{ str_contains($limit->status, 'closed') ? 'bg-blue-100 text-blue-700' : '' }}
                      ">
                        {{ str_replace('_', ' ', $limit->status) }}
                      </span>
                      @if($limit->riwayat->isNotEmpty())
                        <p class="mt-1 mb-0 text-[10px] text-slate-400">
                          {{ $limit->riwayat->count() }}x Perubahan
                        </p>
                      @endif
                    @else
                      <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700">
                        Kosong
                      </span>
                    @endif
                  </td>
                  
                  <td class="px-4 py-4 text-right">
                    @if($limit)
                      <div class="flex flex-col items-end gap-2">
                        @if($limit->status === LimitPotongGajiAnggota::STATUS_DRAFT)
                          <form action="{{ route('periode-potong-gaji.limit.activate', $limit) }}" method="POST">@csrf @method('PATCH')
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald !text-[10px] !py-1 !px-2">Aktifkan Limit</button>
                          </form>
                        @endif
                        @if($limit->status === LimitPotongGajiAnggota::STATUS_ACTIVE)
                          <form action="{{ route('periode-potong-gaji.limit.payoff-payroll', $limit) }}" method="POST">@csrf
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--navy !text-[10px] !py-1 !px-2 mb-1" title="Potong gaji dan lunasi cicilan bulan ini">Bayar via Payroll</button>
                          </form>
                          <form action="{{ route('periode-potong-gaji.limit.close', $limit) }}" method="POST">@csrf @method('PATCH')
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--outline-slate !text-[10px] !py-1 !px-2">Tutup Limit</button>
                          </form>
                        @endif
                        @if($limit->status === LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION)
                          <form action="{{ route('periode-potong-gaji.limit.confirm', $limit) }}" method="POST">@csrf @method('PATCH')
                            <button class="kbsm-btn kbsm-btn--sm kbsm-btn--emerald !text-[10px] !py-1 !px-2">Konfirmasi Selesai</button>
                          </form>
                        @endif
                      </div>
                    @else
                      <span class="text-xs text-slate-400 italic">Buat limit dulu</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </section>
</div>
@endsection
