@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-700">
      {{ session('success') }}
    </div>
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

  <div class="mb-6 flex items-center justify-between">
    <div>
      <h6 class="mb-1 text-lg font-bold text-slate-700">Detail Pinjaman {{ $pinjaman->kode_pinjaman }}</h6>
      <p class="mb-0 text-sm text-slate-400">
        {{ $pinjaman->anggota->nomor_anggota ?? '-' }} - {{ $pinjaman->anggota->karyawan->nama ?? '-' }}
      </p>
    </div>
    <a href="{{ route('pinjaman.index') }}"
      class="inline-block rounded-lg bg-gradient-to-tl from-slate-700 to-slate-500 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
      Kembali
    </a>
  </div>

  <div class="mb-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Pokok</p>
      <p class="mb-0 text-sm font-bold text-slate-700">Rp {{ number_format($pinjaman->jumlah_pinjaman, 0, ',', '.') }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Sisa</p>
      <p class="mb-0 text-sm font-bold text-slate-700">Rp {{ number_format($pinjaman->sisa_pinjaman, 0, ',', '.') }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Dompet</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $pinjaman->dompet->nama_dompet ?? '-' }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Jurnal</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $pinjaman->jurnal?->nomor_bukti ?? '-' }}</p>
    </div>
  </div>

  @php
    $cashAllowed = $pinjaman->status === \App\Models\Pinjaman::STATUS_AKTIF
      && (
        ($pinjaman->anggota?->status ?? null) !== \App\Models\Anggota::STATUS_AKTIF
        || ($pinjaman->anggota?->karyawan?->status_kerja ?? null) !== \App\Models\Karyawan::STATUS_AKTIF
      );
  @endphp

  @if($cashAllowed)
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-700 p-6 text-white shadow-soft-xl">
      <h6 class="mb-1 text-white">Pembayaran Tunai Mantan Karyawan</h6>
      <p class="text-sm text-emerald-50">Nominal tidak dapat diedit; sistem memakai jadwal unpaid paling awal atau seluruh sisa Pinjaman.</p>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <form action="{{ route('pinjaman.cash-schedule', $pinjaman) }}" method="POST" class="rounded-xl bg-white/10 p-4">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-emerald-50">Dompet Kas penerimaan</label>
          <select name="dompet_id" class="mb-3 w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-700">
            @foreach($dompetKas as $dompet)
              <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} · {{ $dompet->akun?->kode_akun ?? 'COA?' }}</option>
            @endforeach
          </select>
          <button class="rounded-lg bg-white px-4 py-2 text-xs font-bold uppercase text-slate-900">Bayar Cicilan Terjadwal</button>
        </form>
        <form action="{{ route('pinjaman.cash-full', $pinjaman) }}" method="POST" class="rounded-xl bg-white/10 p-4"
          onsubmit="return confirm('Lunasi seluruh sisa pinjaman secara tunai?')">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-emerald-50">Dompet Kas penerimaan</label>
          <select name="dompet_id" class="mb-3 w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-700">
            @foreach($dompetKas as $dompet)
              <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} · {{ $dompet->akun?->kode_akun ?? 'COA?' }}</option>
            @endforeach
          </select>
          <button class="rounded-lg bg-emerald-100 px-4 py-2 text-xs font-bold uppercase text-emerald-900">Lunasi Seluruh Sisa Tunai</button>
        </form>
      </div>
    </div>
  @endif

  <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
    <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
      <h6>Jadwal Cicilan</h6>
      <p class="text-sm text-slate-400">Jadwal otomatis read-only; pembayaran dibuat lewat payroll atau alur tunai mantan Karyawan.</p>
    </div>

    <div class="flex-auto px-0 pt-0 pb-2">
      <div style="overflow-x: auto;" class="p-0">
        <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
          <thead class="align-bottom">
            <tr>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Angsuran Ke</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Periode</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Nominal</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Metode</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Pembayaran</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pinjaman->jadwalCicilan as $jadwal)
              <tr>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $jadwal->angsuran_ke }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $jadwal->periode->format('Y-m') }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">Rp {{ number_format($jadwal->nominal_pokok, 0, ',', '.') }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                  <span class="inline-block rounded-1.8 bg-gradient-to-tl from-blue-700 to-cyan-500 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                    {{ $jadwal->status }}
                  </span>
                </td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $jadwal->metode_penyelesaian ?: '-' }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                  @if($jadwal->cicilanPembayaran)
                    <p class="mb-0 text-xs font-bold text-slate-600">CIC-{{ $jadwal->cicilanPembayaran->id }}</p>
                    <p class="mb-0 text-xs text-slate-400">{{ optional($jadwal->cicilanPembayaran->tanggal_bayar)->format('Y-m-d') }}</p>
                  @else
                    -
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="p-4 text-center text-sm text-slate-400">Belum ada jadwal cicilan.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
