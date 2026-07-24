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
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-blue-600">Keuangan B2B</p>
      <h1 class="text-lg font-bold text-slate-700 m-0">Invoice Penagihan Sewa</h1>
      <p class="mt-1 text-sm text-slate-400">Generate tagihan bulanan untuk perusahaan.</p>
    </div>
  </div>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <h2 class="text-base font-bold text-slate-700 m-0">Generate Invoice Baru</h2>
    <form method="POST" action="{{ route('invoice-penagihan.store') }}">
      @csrf
      <div class="grid gap-4 md:grid-cols-4 mt-4">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Perusahaan</label>
          <select name="perusahaan_id" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
            <option value="">- Pilih Perusahaan -</option>
            @foreach($perusahaan as $p)
                <option value="{{ $p->id }}">{{ $p->nama }} ({{ $p->kode }})</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Bulan</label>
          <select name="bulan" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
            @for($i=1; $i<=12; $i++)
                <option value="{{ $i }}" {{ date('n') == $i ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $i, 1)) }}</option>
            @endfor
          </select>
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tahun</label>
          <input type="number" name="tahun" required value="{{ date('Y') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 font-bold text-white hover:bg-blue-700">Generate Invoice</button>
        </div>
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h2 class="text-base font-bold text-slate-700 m-0">Daftar Invoice</h2>
    </div>

    <div style="overflow-x: auto;" class="">
      <table class="w-full min-w-[800px] text-left text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-4 font-bold text-slate-500">Nomor Invoice</th>
            <th class="px-6 py-4 font-bold text-slate-500">Perusahaan</th>
            <th class="px-6 py-4 font-bold text-slate-500">Tanggal</th>
            <th class="px-6 py-4 font-bold text-slate-500">Total Tagihan</th>
            <th class="px-6 py-4 font-bold text-slate-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($invoices as $invoice)
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-bold text-blue-600">{{ $invoice->nomor_invoice }}</td>
              <td class="px-6 py-4 font-semibold text-slate-700">{{ $invoice->perusahaan->nama }}</td>
              <td class="px-6 py-4 text-slate-600">
                <div>Dibuat: {{ \Carbon\Carbon::parse($invoice->tanggal_invoice)->format('d M Y') }}</div>
                <div class="text-xs text-red-500">Jatuh Tempo: {{ \Carbon\Carbon::parse($invoice->jatuh_tempo)->format('d M Y') }}</div>
              </td>
              <td class="px-6 py-4 font-bold text-slate-700">Rp {{ number_format($invoice->total_tagihan, 0, ',', '.') }}</td>
              <td class="px-6 py-4">
                @if($invoice->status === 'paid')
                  <span class="rounded-lg bg-green-100 px-2 py-1 text-xs font-bold text-green-700">LUNAS</span>
                @else
                  <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold text-amber-700">BELUM DIBAYAR</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada invoice.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $invoices->links() }}</div>
  </section>
</div>
@endsection
