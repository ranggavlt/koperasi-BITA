@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft'        => 'kbsm-status kbsm-status--slate',
    'dikonfirmasi' => 'kbsm-status kbsm-status--green',
    'berjalan'     => 'kbsm-status kbsm-status--amber',
    'selesai'      => 'kbsm-status kbsm-status--emerald',
    'dibatalkan'   => 'kbsm-status kbsm-status--slate',
    default        => 'kbsm-status kbsm-status--slate',
  };
  $formAction = $editData ? route('sewa-printer.update', $editData) : route('sewa-printer.store');
  $detailRows = collect(old('details', $editData?->details?->map(fn($d) => [
    'aset_koperasi_id' => $d->aset_koperasi_id,
    'harga_dasar' => (int) $d->harga_dasar,
  ])->all() ?? []))->values();
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))<div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="mb-6">
    <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Finance</p>
    <h1 class="text-2xl font-bold text-slate-700">Sewa Printer</h1>
    <p class="mt-1 text-sm text-slate-400">Kontrak dibuat Finance, margin otomatis 15%, pembayaran penuh dimuka, pendapatan dasar dan margin diakui terpisah saat selesai.</p>
  </div>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <div class="mb-4 flex flex-col gap-1">
      <h2 class="font-bold text-slate-700">{{ $editData ? 'Edit Draft Sewa Printer' : 'Buat Draft Sewa Printer' }}</h2>
      <p class="text-sm text-slate-400">Penyewa dan pembayar otomatis: {{ config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering') }}. Total dihitung ulang oleh server.</p>
    </div>
    <form method="POST" action="{{ $formAction }}" class="grid gap-6">
      @csrf
      @if($editData) @method('PUT') @endif

      {{-- Section 1: Info Utama (2 kolom) --}}
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">PIC Karyawan</label>
          <select name="karyawan_pic_id" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="">Pilih PIC</option>
            @foreach($karyawanOptions as $karyawan)
              <option value="{{ $karyawan->id }}" {{ (string) old('karyawan_pic_id', $editData?->karyawan_pic_id) === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }} - {{ $karyawan->jabatan }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Keterangan</label>
          <input name="keterangan" value="{{ old('keterangan', $editData?->keterangan) }}" placeholder="Opsional" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Mulai</label>
          <input type="date" name="mulai_tanggal" required value="{{ old('mulai_tanggal', $editData?->mulai_tanggal?->toDateString()) }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Selesai</label>
          <input type="date" name="selesai_tanggal" required value="{{ old('selesai_tanggal', $editData?->selesai_tanggal?->toDateString()) }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
      </div>

      {{-- Section 2: Detail Printer --}}
      <div class="rounded-2xl border border-slate-100">
        <div class="border-b border-slate-100 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-700">Detail Printer</h3>
          <p class="text-xs text-slate-400">Isi baris yang digunakan. Margin 15% hanya tampilan; backend tetap menghitung ulang.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[920px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr><th class="px-4 py-3">Printer</th><th class="px-4 py-3">Harga Dasar</th><th class="px-4 py-3">Margin 15%</th><th class="px-4 py-3">Total</th></tr>
            </thead>
            <tbody>
              @for($i = 0; $i < 6; $i++)
                @php $row = $detailRows->get($i, []); @endphp
                <tr class="border-t border-slate-100">
                  <td class="px-4 py-3">
                    <select name="details[{{ $i }}][aset_koperasi_id]" class="sewa-printer-input kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="">Pilih Printer</option>
                      @foreach($printerOptions as $printer)
                        <option value="{{ $printer->id }}" {{ (string) ($row['aset_koperasi_id'] ?? '') === (string) $printer->id ? 'selected' : '' }}>{{ $printer->kode_aset }} - {{ $printer->printer->nomor_seri ?? '-' }} - {{ $printer->merek }} {{ $printer->model }} ({{ $printer->status_label }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td class="px-4 py-3"><input type="number" min="1" name="details[{{ $i }}][harga_dasar]" value="{{ $row['harga_dasar'] ?? '' }}" class="sewa-printer-harga kbsm-focus w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="1000000"></td>
                  <td class="px-4 py-3 text-slate-600"><span class="sewa-printer-margin">Rp 0</span></td>
                  <td class="px-4 py-3 font-bold kbsm-text-navy"><span class="sewa-printer-total">Rp 0</span></td>
                </tr>
              @endfor
            </tbody>
          </table>
        </div>
        <div class="grid gap-3 border-t border-slate-100 bg-slate-50 px-4 py-4 text-sm md:grid-cols-3">
          <div>Total Dasar: <span id="sp-total-dasar" class="font-bold text-slate-700">Rp 0</span></div>
          <div>Total Margin: <span id="sp-total-margin" class="font-bold text-slate-700">Rp 0</span></div>
          <div>Grand Total: <span id="sp-grand-total" class="font-bold kbsm-text-navy">Rp 0</span></div>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <button class="kbsm-btn kbsm-btn--navy">{{ $editData ? 'Simpan Draft' : 'Buat Draft' }}</button>
        @if($editData)<a href="{{ route('sewa-printer.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal Edit</a>@endif
      </div>
    </form>
  </section>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <form method="GET" action="{{ route('sewa-printer.index') }}" class="grid gap-4 md:grid-cols-4">
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Status</label>
        <select name="status" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($statuses as $value => $label)
            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">PIC</label>
        <select name="karyawan_pic_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($karyawanOptions as $karyawan)
            <option value="{{ $karyawan->id }}" {{ (string) request('karyawan_pic_id') === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Periode mulai</label>
        <input type="month" name="periode" value="{{ request('periode') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div class="flex items-end">
        <button class="kbsm-btn kbsm-btn--navy kbsm-btn--full">Filter</button>
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6"><h2 class="font-bold text-slate-700">Daftar Sewa Printer</h2><p class="text-sm text-slate-400">Kontrak confirmed tidak dapat diedit/hapus. Gunakan batal/refund penuh sebelum berjalan jika eligible.</p></div>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[1700px] text-left text-sm">
        <thead class="kbsm-thead">
          <tr>
            <th class="px-6 py-4">Kode</th><th class="px-6 py-4">Perusahaan/PIC</th><th class="px-6 py-4">Periode</th><th class="px-6 py-4">Printer</th><th class="px-6 py-4">Nominal</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Pembayaran</th><th class="px-6 py-4">Posting</th><th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($sewaPrinter as $item)
            <tr class="align-top hover:bg-slate-50">
              <td class="px-6 py-4 font-bold kbsm-text-navy">{{ $item->kode_sewa }}</td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->nama_perusahaan_snapshot }}</div><div class="text-xs text-slate-400">PIC: {{ $item->karyawanPic->nama }} / {{ $item->karyawanPic->status_kerja }}</div></td>
              <td class="px-6 py-4 text-slate-600">{{ $item->mulai_tanggal->format('d/m/Y') }}<br>{{ $item->selesai_tanggal->format('d/m/Y') }}</td>
              <td class="px-6 py-4">
                <div class="mb-1 text-xs font-bold text-slate-500">{{ $item->details->count() }} Printer</div>
                <ul class="space-y-1 text-xs text-slate-500">
                  @foreach($item->details as $detail)
                    <li><span class="font-semibold text-slate-700">{{ $detail->kode_aset_snapshot }}</span> / {{ $detail->nomor_seri_snapshot }} — Rp {{ number_format((float) $detail->harga_dasar, 0, ',', '.') }} + Rp {{ number_format((float) $detail->margin_nominal, 0, ',', '.') }}</li>
                  @endforeach
                </ul>
              </td>
              <td class="px-6 py-4 text-slate-600">
                <div>Dasar: Rp {{ number_format((float) $item->total_harga_dasar, 0, ',', '.') }}</div>
                <div>Margin: Rp {{ number_format((float) $item->total_margin, 0, ',', '.') }}</div>
                <div class="font-bold kbsm-text-navy">Grand: Rp {{ number_format((float) $item->grand_total, 0, ',', '.') }}</div>
              </td>
              <td class="px-6 py-4"><span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span><div class="mt-2 text-xs text-slate-400">Payment: {{ $item->status_pembayaran }}</div></td>
              <td class="px-6 py-4 text-xs text-slate-500">
                @if($item->pembayaran)
                  {{ $item->pembayaran->metode_pembayaran }} / {{ $item->pembayaran->dompet->nama_dompet ?? '-' }}<br>{{ $item->pembayaran->paid_at->format('d/m/Y H:i') }}
                @else
                  Belum bayar
                @endif
              </td>
              <td class="px-6 py-4 text-xs text-slate-500">
                <div>Jurnal kontrak: {{ $item->jurnal->count() }}</div>
                <div>Jurnal bayar/refund: {{ $item->pembayaran?->jurnal?->count() ?? 0 }}</div>
                <div>Mutasi: {{ $item->pembayaran?->mutasiKas?->count() ?? 0 }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex max-w-[520px] flex-col gap-2">
                  @if($item->status === 'draft')
                    <div class="flex flex-wrap gap-2">
                      <a href="{{ route('sewa-printer.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit Draft</a>
                      <form method="POST" action="{{ route('sewa-printer.confirm', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Konfirmasi</button></form>
                    </div>
                    <form method="POST" action="{{ route('sewa-printer.cancel', $item) }}" class="flex gap-2" onsubmit="return confirm('Batalkan draft kontrak ini?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</button>
                    </form>
                  @endif

                  @if($item->status === 'dikonfirmasi' && $item->status_pembayaran === 'belum_bayar')
                    <form method="POST" action="{{ route('sewa-printer.pay', $item) }}" class="grid gap-2 md:grid-cols-4">
                      @csrf
                      <select name="metode_pembayaran" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="tunai">Tunai</option>
                        <option value="transfer_bank">Transfer Bank</option>
                      </select>
                      <select name="dompet_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="">Dompet</option>
                        @foreach($dompetOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }})</option>
                        @endforeach
                      </select>
                      <input type="number" name="jumlah_bayar" min="1" required value="{{ (int) $item->grand_total }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Catat Bayar</button>
                    </form>
                  @endif

                  @if($item->status === 'dikonfirmasi' && $item->status_pembayaran === 'paid')
                    <form method="POST" action="{{ route('sewa-printer.start', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--amber kbsm-btn--sm">Mulai</button></form>
                  @endif

                  @if($item->status === 'berjalan')
                    <form method="POST" action="{{ route('sewa-printer.complete', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Selesai</button></form>
                  @endif

                  @if($item->status === 'dikonfirmasi')
                    <form method="POST" action="{{ route('sewa-printer.cancel', $item) }}" class="flex gap-2" onsubmit="return confirm('Batalkan/refund penuh kontrak ini jika eligible?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan/refund" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batal/Refund</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="px-6 py-10 text-center text-slate-400">Belum ada transaksi Sewa Printer.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $sewaPrinter->links() }}</div>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
    const refresh = () => {
      let dasarTotal = 0;
      let marginTotal = 0;
      document.querySelectorAll('.sewa-printer-harga').forEach((input) => {
        const row = input.closest('tr');
        const dasar = parseInt(input.value || '0', 10) || 0;
        const margin = Math.floor(((dasar * 15) + 50) / 100);
        dasarTotal += dasar;
        marginTotal += margin;
        row.querySelector('.sewa-printer-margin').textContent = rupiah(margin);
        row.querySelector('.sewa-printer-total').textContent = rupiah(dasar + margin);
      });
      document.getElementById('sp-total-dasar').textContent = rupiah(dasarTotal);
      document.getElementById('sp-total-margin').textContent = rupiah(marginTotal);
      document.getElementById('sp-grand-total').textContent = rupiah(dasarTotal + marginTotal);
    };
    document.querySelectorAll('.sewa-printer-harga').forEach((input) => input.addEventListener('input', refresh));
    refresh();
  });
</script>
@endsection
