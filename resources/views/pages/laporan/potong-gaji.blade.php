@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="mb-6 rounded-2xl bg-white p-6 shadow-soft-xl">
    <h6 class="text-slate-700">Laporan Potong Gaji Bulanan</h6>
    <p class="text-sm text-slate-400">Read model dari periode, limit, ledger pemakaian, kredit refund, pembayaran, reversal, Mutasi Kas, dan Jurnal.</p>

    <form class="mt-4 flex flex-wrap items-end gap-3" method="GET" action="{{ route('laporan.potong-gaji') }}">
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-700">Periode</label>
        <input type="month" name="periode" value="{{ $periode }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-700">Anggota</label>
        <select name="anggota_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
          <option value="">Semua</option>
          @foreach($anggotaOptions as $anggota)
            <option value="{{ $anggota->id }}" @selected(request('anggota_id') == $anggota->id)>
              {{ $anggota->nomor_anggota }} - {{ $anggota->karyawan?->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-700">Status</label>
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
          <option value="">Semua</option>
          @foreach(['draft','active','closed_pending_confirmation','confirmed','cancelled'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-700">Kategori</label>
        <select name="kategori" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
          <option value="">Semua</option>
          @foreach($kategoriOptions as $key => $label)
            <option value="{{ $key }}" @selected(request('kategori') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <button class="rounded-lg bg-gradient-to-tl from-emerald-600 to-slate-800 px-6 py-3 text-xs font-bold uppercase text-white">Tampilkan</button>
      <a href="{{ route('rekonsiliasi-potong-gaji.index', ['periode' => $periode]) }}" class="rounded-lg border border-emerald-600 px-6 py-3 text-xs font-bold uppercase text-emerald-700">Rekonsiliasi</a>
    </form>
  </div>

  @php
    $cards = [
      ['label' => 'Gross Payroll', 'value' => $summary['gross_payroll']],
      ['label' => 'Kredit Refund', 'value' => $summary['kredit_refund']],
      ['label' => 'Net Payroll', 'value' => $summary['net_payroll']],
      ['label' => 'Diterima Bank', 'value' => $summary['total_diterima_bank']],
      ['label' => 'Outstanding', 'value' => $summary['total_outstanding']],
      ['label' => 'Released/Reversed', 'value' => $summary['total_released_reversed']],
    ];
  @endphp

  <div class="mb-6 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
    @foreach($cards as $card)
      <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
        <p class="mb-1 text-xs font-bold uppercase text-slate-400">{{ $card['label'] }}</p>
        <h5 class="mb-0 text-slate-700">Rp {{ number_format($card['value'], 0, ',', '.') }}</h5>
      </div>
    @endforeach
  </div>

  <div class="mb-6 rounded-2xl bg-white shadow-soft-xl">
    <div class="p-6 pb-0">
      <h6 class="text-slate-700">Ringkasan per Anggota</h6>
      <p class="text-sm text-slate-400">Pinjaman baru tidak dihitung sebagai penggunaan limit; hanya Cicilan yang masuk payroll.</p>
    </div>
    <div style="overflow-x: auto;" class="p-0">
      <table class="mb-0 w-full text-sm text-slate-600">
        <thead>
          <tr class="text-left text-xxs uppercase text-slate-400">
            <th class="px-6 py-3">Anggota</th>
            <th class="px-6 py-3">Limit</th>
            <th class="px-6 py-3">Cicilan</th>
            <th class="px-6 py-3">Simpanan Pokok</th>
            <th class="px-6 py-3">POS</th>
            <th class="px-6 py-3">Kredit</th>
            <th class="px-6 py-3">Net</th>
            <th class="px-6 py-3">Sisa Kapasitas</th>
            <th class="px-6 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($laporan as $row)
            <tr>
              <td class="border-b px-6 py-3">
                <div class="font-semibold text-slate-700">{{ $row->nomor_anggota }}</div>
                <div class="text-xs text-slate-400">{{ $row->nama }}</div>
              </td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->limit_nominal, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->cicilan, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->simpanan_pokok, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->pos, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->kredit_refund, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3 font-bold text-emerald-700">Rp {{ number_format($row->net_payroll, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->sisa_kapasitas, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">{{ $row->status_limit }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="p-6 text-center text-slate-400">Belum ada limit/ledger untuk periode ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="rounded-2xl bg-white shadow-soft-xl">
    <div class="p-6 pb-0">
      <h6 class="text-slate-700">Detail Ledger</h6>
    </div>
    <div style="overflow-x: auto;" class="p-0">
      <table class="mb-0 w-full text-sm text-slate-600">
        <thead>
          <tr class="text-left text-xxs uppercase text-slate-400">
            <th class="px-6 py-3">Anggota</th>
            <th class="px-6 py-3">Kategori</th>
            <th class="px-6 py-3">Sumber</th>
            <th class="px-6 py-3">Tanggal</th>
            <th class="px-6 py-3">Nominal</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Reversal</th>
          </tr>
        </thead>
        <tbody>
          @forelse($details as $detail)
            <tr>
              <td class="border-b px-6 py-3">{{ $detail->anggota?->nomor_anggota }} - {{ $detail->anggota?->karyawan?->nama }}</td>
              <td class="border-b px-6 py-3">{{ $detail->kategori }}</td>
              <td class="border-b px-6 py-3">{{ $detail->kode_sumber }}</td>
              <td class="border-b px-6 py-3">{{ optional($detail->tanggal)->format('d/m/Y H:i') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($detail->nominal, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">{{ $detail->status }}</td>
              <td class="border-b px-6 py-3">{{ $detail->reversal?->kode_reversal ?? '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="p-6 text-center text-slate-400">Tidak ada ledger sesuai filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
