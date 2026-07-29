@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $jadwalRows = collect($detailReport['jadwalRows'] ?? []);
  $payments = collect($detailReport['payments'] ?? []);
  $detailSummary = $detailReport['summary'] ?? [];
  $statusClass = [
    \App\Models\Pinjaman::STATUS_DRAFT => 'kbsm-status--slate',
    \App\Models\Pinjaman::STATUS_DIAJUKAN => 'kbsm-status--amber',
    \App\Models\Pinjaman::STATUS_DISETUJUI => 'kbsm-status--green',
    \App\Models\Pinjaman::STATUS_AKTIF => 'kbsm-status--amber',
    \App\Models\Pinjaman::STATUS_LUNAS => 'kbsm-status--green',
    \App\Models\Pinjaman::STATUS_DITOLAK => 'kbsm-status--red',
    \App\Models\Pinjaman::STATUS_DIBATALKAN => 'kbsm-status--slate',
  ];
  $cashAllowed = $pinjaman->status === \App\Models\Pinjaman::STATUS_AKTIF
    && (
      ($pinjaman->anggota?->status ?? null) !== \App\Models\Anggota::STATUS_AKTIF
      || ($pinjaman->anggota?->karyawan?->status_kerja ?? null) !== \App\Models\Karyawan::STATUS_AKTIF
    );
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-slate-700">Detail Pinjaman {{ $pinjaman->kode_pinjaman }}</h1>
      <p class="mt-1 text-sm text-slate-400">
        {{ $pinjaman->anggota->nomor_anggota ?? '-' }} - {{ $pinjaman->anggota->karyawan->nama ?? '-' }}
      </p>
      @if($detailSummary['old_cycle'] ?? false)
        <div class="mt-3 inline-flex rounded-full border border-amber-200 bg-amber-100 px-3 py-1 text-xs font-bold uppercase text-amber-700">
          Kewajiban Siklus Lama â€” pembayaran tunai
        </div>
      @endif
    </div>
    <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Kembali</a>
  </div>

  <div class="mb-6 grid gap-4 md:grid-cols-6">
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Nominal</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $money($pinjaman->jumlah_pinjaman) }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Sisa</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $money($pinjaman->sisa_pinjaman) }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Total Offset</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $money($detailSummary['total_offset'] ?? 0) }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Total Pembayaran</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $money($detailSummary['total_pembayaran'] ?? 0) }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Status</p>
      <span class="kbsm-status {{ $statusClass[$pinjaman->status] ?? 'kbsm-status--slate' }}">{{ $pinjaman->status_label }}</span>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Jurnal</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $pinjaman->jurnal?->nomor_bukti ?? 'Belum ada' }}</p>
    </div>
  </div>

  <div class="mb-6 grid gap-6 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl lg:col-span-2">
      <h2 class="mb-4 text-base font-bold text-slate-700">Informasi Pengajuan</h2>
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Siklus Keanggotaan</p>
          <p class="text-sm text-slate-700">
            Siklus {{ $pinjaman->siklusKeanggotaan?->siklus_ke ?? '-' }}
            @if($pinjaman->siklusKeanggotaan?->status)
              &bull; {{ ucfirst($pinjaman->siklusKeanggotaan->status) }}
            @endif
          </p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Tanggal Pengajuan</p>
          <p class="text-sm text-slate-700">{{ optional($pinjaman->tanggal_pengajuan)->format('d/m/Y') ?? '-' }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Tanggal Pencairan</p>
          <p class="text-sm text-slate-700">{{ optional($pinjaman->tanggal_pinjaman)->format('d/m/Y') ?? '-' }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Plafon Snapshot</p>
          <p class="text-sm text-slate-700">{{ $money($pinjaman->plafon_pinjaman_snapshot) }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Tenor & Bunga</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->tenor_bulan }} bulan &bull; 0%</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Dompet Pencairan</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->dompet->nama_dompet ?? 'Belum dicairkan' }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Mutasi Kas</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->mutasiKas?->keterangan ?? 'Belum ada' }}</p>
        </div>
        <div class="md:col-span-2">
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Keterangan</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->keterangan ?: '-' }}</p>
        </div>
        @if($pinjaman->rejection_reason || $pinjaman->cancellation_reason)
          <div class="md:col-span-2 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
            {{ $pinjaman->rejection_reason ?: $pinjaman->cancellation_reason }}
          </div>
        @endif
      </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
      <h2 class="mb-4 text-base font-bold text-slate-700">Timeline Audit</h2>
      <div class="space-y-3 text-sm text-slate-600">
        <p>Dibuat: {{ optional($pinjaman->created_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Diajukan: {{ optional($pinjaman->submitted_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Disetujui: {{ optional($pinjaman->approved_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Ditolak: {{ optional($pinjaman->rejected_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Dibatalkan: {{ optional($pinjaman->cancelled_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Dicairkan: {{ optional($pinjaman->disbursed_at)->format('d/m/Y H:i') ?? '-' }}</p>
      </div>
    </div>
  </div>

  @if(in_array($pinjaman->status, [\App\Models\Pinjaman::STATUS_DRAFT, \App\Models\Pinjaman::STATUS_DIAJUKAN, \App\Models\Pinjaman::STATUS_DISETUJUI], true))
    <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
      <h2 class="mb-2 text-base font-bold text-slate-700">Aksi Finance</h2>
      <p class="mb-4 text-sm text-slate-400">Aksi sebelum pencairan tidak membuat Mutasi Kas, Jurnal, Jadwal Cicilan, atau ledger payroll.</p>

      <div class="grid gap-4 lg:grid-cols-3">
        @if($pinjaman->status === \App\Models\Pinjaman::STATUS_DRAFT)
          <div class="rounded-xl border border-slate-100 p-4">
            <p class="mb-3 text-sm text-slate-600">Draft masih dapat diedit atau diajukan.</p>
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('pinjaman.edit', $pinjaman) }}" class="kbsm-btn kbsm-btn--outline-slate">Edit Draft</a>
              <form method="POST" action="{{ route('pinjaman.submit', $pinjaman) }}">@csrf<button class="kbsm-btn kbsm-btn--green">Ajukan</button></form>
            </div>
          </div>
        @endif

        @if($pinjaman->status === \App\Models\Pinjaman::STATUS_DIAJUKAN)
          <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
            <p class="mb-3 text-sm text-emerald-800">Setujui pengajuan setelah validasi dokumen di luar aplikasi.</p>
            <form method="POST" action="{{ route('pinjaman.approve', $pinjaman) }}">@csrf<button class="kbsm-btn kbsm-btn--green">Setujui</button></form>
          </div>
          <form method="POST" action="{{ route('pinjaman.reject', $pinjaman) }}" class="rounded-xl border border-red-100 bg-red-50 p-4">
            @csrf
            <label class="mb-2 block text-xs font-bold uppercase text-red-700">Alasan Penolakan</label>
            <textarea name="alasan" rows="3" class="mb-3 block w-full rounded-lg border border-red-200 px-3 py-2 text-sm" required></textarea>
            <button class="kbsm-btn kbsm-btn--outline-red">Tolak</button>
          </form>
        @endif

        @if($pinjaman->status === \App\Models\Pinjaman::STATUS_DISETUJUI)
          <form method="POST" action="{{ route('pinjaman.disburse', $pinjaman) }}" class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 lg:col-span-2">
            @csrf
            <h3 class="mb-3 text-sm font-bold text-emerald-900">Cairkan Pinjaman</h3>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Pencairan</label>
                <input type="date" name="tanggal_pencairan" value="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                  class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" required>
              </div>
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Dompet Sumber Dana</label>
                <select name="dompet_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" required>
                  <option value="">-- Pilih Dompet --</option>
                  @foreach($dompet as $item)
                    <option value="{{ $item->id }}">{{ $item->nama_dompet }} &bull; Saldo {{ $money($item->saldo) }} &bull; {{ $item->akun?->kode_akun ?? 'COA?' }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="my-4 rounded-lg bg-white p-3 text-sm text-slate-600">
              Estimasi cicilan: {{ $pinjaman->tenor_bulan }} bulan, total {{ $money($pinjaman->jumlah_pinjaman) }}, bunga 0%.
            </div>
            <button class="kbsm-btn kbsm-btn--navy" onclick="return confirm('Cairkan Pinjaman ini dan buat Mutasi/Jurnal/Jadwal?')">Cairkan Pinjaman</button>
          </form>
        @endif

        <form method="POST" action="{{ route('pinjaman.cancel', $pinjaman) }}" class="rounded-xl border border-red-100 p-4">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-red-700">Alasan Pembatalan</label>
          <textarea name="alasan" rows="3" class="mb-3 block w-full rounded-lg border border-red-200 px-3 py-2 text-sm" required></textarea>
          <button class="kbsm-btn kbsm-btn--outline-red">Batalkan</button>
        </form>
      </div>
    </div>
  @endif

  @if($cashAllowed)
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-700 p-6 text-white shadow-soft-xl">
      <h2 class="mb-1 text-base font-bold text-white">Pembayaran Tunai Mantan Karyawan</h2>
      <p class="text-sm text-emerald-50">Nominal tidak dapat diedit; sistem memakai jadwal unpaid paling awal atau seluruh sisa Pinjaman.</p>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <form action="{{ route('pinjaman.cash-schedule', $pinjaman) }}" method="POST" class="rounded-xl bg-white/10 p-4">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-emerald-50">Dompet Kas penerimaan</label>
          <select name="dompet_id" class="mb-3 w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-700">
            @foreach($dompetKas as $kas)
              <option value="{{ $kas->id }}">{{ $kas->nama_dompet }} &bull; {{ $kas->akun?->kode_akun ?? 'COA?' }}</option>
            @endforeach
          </select>
          <button class="rounded-lg bg-white px-4 py-2 text-xs font-bold uppercase text-slate-900">Bayar Cicilan Terjadwal</button>
        </form>
        <form action="{{ route('pinjaman.cash-full', $pinjaman) }}" method="POST" class="rounded-xl bg-white/10 p-4"
          onsubmit="return confirm('Lunasi seluruh sisa pinjaman secara tunai?')">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-emerald-50">Dompet Kas penerimaan</label>
          <select name="dompet_id" class="mb-3 w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-700">
            @foreach($dompetKas as $kas)
              <option value="{{ $kas->id }}">{{ $kas->nama_dompet }} &bull; {{ $kas->akun?->kode_akun ?? 'COA?' }}</option>
            @endforeach
          </select>
          <button class="rounded-lg bg-emerald-100 px-4 py-2 text-xs font-bold uppercase text-emerald-900">Lunasi Seluruh Sisa Tunai</button>
        </form>
      </div>
    </div>
  @endif

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Jadwal Cicilan</h2>
      <p class="kbsm-business-panel__copy">Jadwal otomatis read-only; status payroll dibaca dari ledger pemakaian potong gaji.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Angsuran</th>
            <th>Periode</th>
            <th class="kbsm-business-table__right">Pokok Awal</th>
            <th class="kbsm-business-table__right">Offset</th>
            <th class="kbsm-business-table__right">Sisa Tagihan</th>
            <th>Status Cicilan</th>
            <th>Status Payroll</th>
            <th>Pembayaran</th>
          </tr>
        </thead>
        <tbody>
          @forelse($jadwalRows as $row)
            <tr>
              <td>Ke-{{ $row->jadwal->angsuran_ke }}</td>
              <td>{{ optional($row->periode)->format('Y-m') ?? '-' }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_pokok) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_offset) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_sisa) }}</td>
              <td><span class="{{ $row->status_class }}">{{ $row->status_label }}</span></td>
              <td>
                <span class="{{ $row->payroll_status_class }}">{{ $row->payroll_status_label }}</span>
                @if($row->payroll_nominal > 0)
                  <div class="kbsm-business-muted">{{ $money($row->payroll_nominal) }}</div>
                @endif
              </td>
              <td>
                @if($row->payment)
                  <span class="kbsm-business-code">CIC-{{ $row->payment->id }}</span>
                  <div class="kbsm-business-muted">{{ $row->metode_pembayaran_label }} &bull; {{ optional($row->tanggal_pembayaran)->format('d/m/Y') ?? '-' }}</div>
                @else
                  <span class="kbsm-business-muted">Belum ada pembayaran</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="kbsm-business-empty">Belum ada jadwal cicilan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <div class="grid gap-6 lg:grid-cols-2">
    <section class="kbsm-business-panel">
      <div class="kbsm-business-panel__header">
        <h2 class="kbsm-business-panel__title">Histori Pembayaran</h2>
        <p class="kbsm-business-panel__copy">Bukti pembayaran tunai/payroll; koreksi ditampilkan sebagai status Dikoreksi.</p>
      </div>
      <div class="kbsm-business-table-wrap">
        <table class="kbsm-business-detail-table">
          <thead><tr><th>Kode</th><th>Tanggal</th><th>Metode</th><th>Nominal</th><th>Status</th></tr></thead>
          <tbody>
            @forelse($payments as $payment)
              <tr>
                <td>CIC-{{ $payment->id }}</td>
                <td>{{ optional($payment->tanggal_bayar)->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $payment->metode_pembayaran === 'potong_gaji' ? 'Potong Gaji' : 'Tunai' }}</td>
                <td class="kbsm-business-amount">{{ $money($payment->jumlah_cicilan) }}</td>
                <td>{{ $payment->status === 'reversed' ? 'Dikoreksi' : ($payment->status === 'sudah_bayar' ? 'Sudah Dibayar' : ucfirst($payment->status)) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="kbsm-business-empty">Belum ada pembayaran cicilan.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="kbsm-business-panel">
      <div class="kbsm-business-panel__header">
        <h2 class="kbsm-business-panel__title">Mutasi dan Jurnal Terkait</h2>
        <p class="kbsm-business-panel__copy">Ringkasan pencairan dan pembayaran yang sudah diposting.</p>
      </div>
      <div class="p-5">
        <div class="mb-4 rounded-xl border border-slate-100 p-4">
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Mutasi Pencairan</p>
          <p class="mb-0 text-sm text-slate-700">{{ $pinjaman->mutasiKas?->tanggal ?? '-' }} &bull; {{ $pinjaman->mutasiKas?->dompet?->nama_dompet ?? $pinjaman->dompet?->nama_dompet ?? '-' }} &bull; {{ $pinjaman->mutasiKas ? $money($pinjaman->mutasiKas->jumlah) : '-' }}</p>
        </div>
        <div class="mb-4 rounded-xl border border-slate-100 p-4">
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Jurnal Pencairan</p>
          <p class="mb-0 text-sm text-slate-700">{{ $pinjaman->jurnal?->nomor_bukti ?? 'Belum ada' }}</p>
          @if($pinjaman->jurnal)
            <p class="mt-1 text-xs text-slate-400">Debit {{ $money($pinjaman->jurnal->details->sum('debit')) }} &bull; Kredit {{ $money($pinjaman->jurnal->details->sum('kredit')) }}</p>
          @endif
        </div>
        <div class="rounded-xl border border-slate-100 p-4">
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Posting Pembayaran Cicilan</p>
          <p class="mb-0 text-sm text-slate-700">{{ $payments->where('status', 'sudah_bayar')->count() }} pembayaran &bull; {{ $money($payments->where('status', 'sudah_bayar')->sum(fn ($row) => (float) $row->jumlah_cicilan)) }}</p>
        </div>
      </div>
    </section>
  </div>
</div>
@endsection
