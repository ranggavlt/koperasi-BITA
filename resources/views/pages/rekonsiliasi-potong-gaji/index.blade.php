@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="mb-6 rounded-2xl bg-white p-6 shadow-soft-xl">
    <h6 class="text-slate-700">Rekonsiliasi Potong Gaji</h6>
    <form class="mt-4 flex flex-wrap items-end gap-3" method="GET">
      <input type="month" name="periode" value="{{ $periode }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
      <button class="rounded-lg bg-gradient-to-tl from-emerald-600 to-slate-800 px-6 py-3 text-xs font-bold uppercase text-white">Tampilkan</button>
    </form>
  </div>

  <div class="mb-6 rounded-2xl bg-white p-6 shadow-soft-xl">
    <p class="mb-2 text-xs font-bold uppercase text-slate-400">Status</p>
    <h4 class="{{ $rekonsiliasi['status'] === 'balanced' ? 'text-emerald-700' : 'text-red-600' }}">{{ $rekonsiliasi['status'] }}</h4>
    <p class="text-sm text-slate-400">Mismatch tidak diperbaiki otomatis; selisih ditampilkan untuk review manual.</p>
  </div>

  <div class="grid gap-4 md:grid-cols-3">
    @foreach([
      'gross_kewajiban' => 'Gross Kewajiban',
      'kredit_refund_diterapkan' => 'Kredit Refund',
      'net_payroll' => 'Net Payroll',
      'mutasi_kas_masuk' => 'Mutasi Kas Masuk',
      'debit_bank_jurnal' => 'Debit Bank Jurnal',
      'kredit_piutang_jurnal' => 'Kredit Piutang',
      'saldo_outstanding' => 'Saldo Outstanding',
      'total_reversal' => 'Total Reversal',
    ] as $key => $label)
      <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
        <p class="text-xs font-bold uppercase text-slate-400">{{ $label }}</p>
        <h5>Rp {{ number_format($rekonsiliasi[$key], 0, ',', '.') }}</h5>
      </div>
    @endforeach
  </div>

  <div class="mt-6 rounded-2xl bg-white p-6 shadow-soft-xl">
    <h6>Selisih</h6>
    @foreach($rekonsiliasi['differences'] as $key => $diff)
      <div class="flex justify-between border-b py-2 text-sm">
        <span>{{ $key }}</span>
        <span class="{{ abs($diff) < 0.01 ? 'text-emerald-700' : 'text-red-600' }}">Rp {{ number_format($diff, 0, ',', '.') }}</span>
      </div>
    @endforeach
  </div>
</div>
@endsection
