@extends('layout.main')

@section('content')
@php
  use App\Models\LimitPotongGajiAnggota;
  use App\Models\PemakaianPotongGaji;
  use App\Models\Pinjaman;

  $fmt = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $periodeLabel = $selectedPeriode ? $selectedPeriode->periode->locale('id')->translatedFormat('F Y') : '-';
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-lg bg-emerald-100 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="grid gap-6 lg:grid-cols-3">
    <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-700 p-6 text-white shadow-soft-xl lg:col-span-2">
      <p class="mb-2 text-xs font-bold uppercase tracking-widest text-emerald-100">Payroll KBSM</p>
      <h3 class="mb-1 text-2xl font-bold">Periode Potong Gaji {{ $periodeLabel }}</h3>
      <p class="mb-0 text-sm text-emerald-50">
        Kelola limit bulanan, reservasi cicilan, penutupan, dan konfirmasi payroll per Anggota.
      </p>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-soft-xl">
      <form action="{{ route('periode-potong-gaji.store') }}" method="POST" class="space-y-3">
        @csrf
        <label class="block text-xs font-bold uppercase text-slate-700">Buat / buka periode</label>
        <input type="month" name="periode" value="{{ old('periode', now(config('app.timezone'))->format('Y-m')) }}"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-slate-700">
        <button class="w-full rounded-lg bg-gradient-to-tl from-slate-900 to-emerald-600 px-4 py-2 text-xs font-bold uppercase text-white">
          Siapkan Periode
        </button>
      </form>
    </div>
  </div>

  <div class="mt-6 rounded-2xl bg-white p-4 shadow-soft-xl">
    <div class="flex flex-wrap items-center gap-2">
      @foreach($periodeList as $periode)
        <a href="{{ route('periode-potong-gaji.index', ['periode_id' => $periode->id]) }}"
          class="rounded-xl px-4 py-2 text-xs font-bold uppercase {{ $selectedPeriode && $selectedPeriode->id === $periode->id ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' }}">
          {{ $periode->periode->format('M Y') }} Â· {{ $periode->status }} Â· {{ $periode->limits_count }} limit
        </a>
      @endforeach
    </div>
    <div class="mt-3">{{ $periodeList->links() }}</div>
  </div>

  @if($selectedPeriode)
    <div class="mt-6 rounded-2xl bg-white shadow-soft-xl">
      <div class="border-b border-slate-100 p-6">
        <h6 class="mb-1">Limit Anggota Periode {{ $periodeLabel }}</h6>
        <p class="mb-0 text-sm text-slate-400">Anggota tanpa limit tampil sebagai â€œBelum dikonfigurasiâ€, bukan Rp0.</p>
      </div>

      <div style="overflow-x: auto;" class="">
        <table class="w-full text-left text-sm text-slate-600">
          <thead class="bg-slate-50 text-xs uppercase text-slate-400">
            <tr>
              <th class="px-4 py-3">Anggota</th>
              <th class="px-4 py-3">Limit</th>
              <th class="px-4 py-3">Cicilan periode</th>
              <th class="px-4 py-3">Reserved</th>
              <th class="px-4 py-3">Consumed</th>
              <th class="px-4 py-3">Sisa</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Bank penerimaan</th>
              <th class="px-4 py-3">Aksi</th>
            </tr>
          </thead>
          <tbody>
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
              <tr class="border-b border-slate-100 align-top">
                <td class="px-4 py-4">
                  <p class="mb-0 font-bold text-slate-700">{{ $anggota->nomor_anggota }}</p>
                  <p class="mb-0 text-xs text-slate-500">{{ $anggota->karyawan->nama ?? '-' }}</p>
                  <p class="mb-0 text-xs text-slate-400">{{ $anggota->status }} / {{ $anggota->karyawan->status_kerja ?? '-' }}</p>
                </td>
                <td class="px-4 py-4">
                  @if($limit)
                    <form action="{{ route('periode-potong-gaji.limit.update', $limit) }}" method="POST" class="space-y-2">
                      @csrf
                      @method('PATCH')
                      <input type="number" name="limit_nominal" min="0" value="{{ (int) $limit->limit_nominal }}"
                        class="w-36 rounded-lg border border-gray-300 px-3 py-2 text-sm" {{ $canEdit ? '' : 'readonly' }}>
                      @if($canEdit)
                        <input type="text" name="alasan" value="Penyesuaian limit {{ $periodeLabel }}" class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-xs">
                        <button class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold uppercase text-white">Update</button>
                      @endif
                    </form>
                  @else
                    <form action="{{ route('periode-potong-gaji.limit.store') }}" method="POST" class="space-y-2">
                      @csrf
                      <input type="hidden" name="periode_id" value="{{ $selectedPeriode->id }}">
                      <input type="hidden" name="anggota_id" value="{{ $anggota->id }}">
                      <input type="number" name="limit_nominal" min="0" placeholder="Nominal limit" class="w-36 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                      <input type="text" name="alasan" value="Limit awal {{ $periodeLabel }}" class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-xs">
                      <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold uppercase text-white">Buat</button>
                    </form>
                    <p class="mt-1 text-xs font-bold text-amber-600">Belum dikonfigurasi</p>
                  @endif
                </td>
                <td class="px-4 py-4">{{ $jadwalPeriode ? $fmt($jadwalPeriode->nominal_pokok) : '-' }}</td>
                <td class="px-4 py-4">{{ $fmt($reserved) }}</td>
                <td class="px-4 py-4">{{ $fmt($consumed) }}</td>
                <td class="px-4 py-4">{{ $limit ? $fmt($sisa) : '-' }}</td>
                <td class="px-4 py-4">
                  @if($limit)
                    <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold uppercase text-slate-600">{{ $limit->status }}</span>
                    @if($limit->riwayat->isNotEmpty())
                      <p class="mt-2 mb-0 text-xs text-slate-400">
                        Histori: {{ $limit->riwayat->count() }} perubahan
                      </p>
                    @endif
                  @else
                    <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-700">Belum dikonfigurasi</span>
                  @endif
                </td>
                <td class="px-4 py-4">{{ $limit?->dompetPenerimaan?->nama_dompet ?? '-' }}</td>
                <td class="px-4 py-4">
                  @if($limit)
                    <div class="flex flex-col gap-2">
                      @if($limit->status === LimitPotongGajiAnggota::STATUS_DRAFT)
                        <form action="{{ route('periode-potong-gaji.limit.activate', $limit) }}" method="POST">@csrf @method('PATCH')
                          <button class="rounded-lg bg-gradient-to-tl from-emerald-700 to-lime-500 px-3 py-2 text-xs font-bold uppercase text-white">Aktifkan</button>
                        </form>
                      @endif
                      @if($limit->status === LimitPotongGajiAnggota::STATUS_ACTIVE)
                        <form action="{{ route('periode-potong-gaji.limit.payoff-payroll', $limit) }}" method="POST">@csrf
                          <button class="rounded-lg bg-gradient-to-tl from-blue-800 to-emerald-500 px-3 py-2 text-xs font-bold uppercase text-white">Lunasi via Payroll</button>
                        </form>
                        <form action="{{ route('periode-potong-gaji.limit.close', $limit) }}" method="POST">@csrf @method('PATCH')
                          <button class="rounded-lg bg-gradient-to-tl from-slate-800 to-slate-500 px-3 py-2 text-xs font-bold uppercase text-white">Tutup</button>
                        </form>
                      @endif
                      @if($limit->status === LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION)
                        <form action="{{ route('periode-potong-gaji.limit.confirm', $limit) }}" method="POST">@csrf @method('PATCH')
                          <button class="rounded-lg bg-gradient-to-tl from-emerald-800 to-teal-400 px-3 py-2 text-xs font-bold uppercase text-white">Konfirmasi</button>
                        </form>
                      @endif
                    </div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
