@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft' => 'bg-slate-100 text-slate-600',
    'posted' => 'bg-green-100 text-green-700',
    'reversed' => 'bg-amber-100 text-amber-700',
    default => 'bg-slate-100 text-slate-600',
  };
  $formAction = $editData ? route('beban-operasional.update', $editData) : route('beban-operasional.store');
  $detailRows = collect(old('details', $editData?->details?->map(fn($d) => [
    'akun_id' => $d->akun_id,
    'aset_koperasi_id' => $d->aset_koperasi_id,
    'keterangan' => $d->keterangan,
    'nominal' => (int) $d->nominal,
  ])->all() ?? []))->values();
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="mb-6">
    <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Finance</p>
    <h1 class="text-2xl font-bold text-slate-700">Beban Operasional</h1>
    <p class="mt-1 text-sm text-slate-400">Beban dibayar langsung dari Dompet Kas/Bank. Posted immutable; koreksi memakai reversal penuh dan audit trail.</p>
  </div>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <div class="mb-4">
      <h2 class="font-bold text-slate-700">{{ $editData ? 'Edit Draft Beban Operasional' : 'Buat Draft Beban Operasional' }}</h2>
      <p class="text-sm text-slate-400">Kode BOP, total, saldo Dompet, Mutasi Kas, dan Jurnal dihitung ulang oleh server.</p>
    </div>

    <form method="POST" action="{{ $formAction }}" class="grid gap-4">
      @csrf
      @if($editData) @method('PUT') @endif

      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Beban</label>
          <input type="date" name="tanggal_beban" required value="{{ old('tanggal_beban', $editData?->tanggal_beban?->toDateString() ?? now()->toDateString()) }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Keterangan Header</label>
          <input name="keterangan" value="{{ old('keterangan', $editData?->keterangan) }}" placeholder="Opsional, mis. operasional kantor bulan ini" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100">
        <div class="border-b border-slate-100 px-4 py-3">
          <h3 class="text-sm font-bold text-slate-700">Detail Beban</h3>
          <p class="text-xs text-slate-400">Pilih akun kategori Beban aktif. Aset opsional untuk biaya terkait Mobil/Printer.</p>
        </div>
        <div style="overflow-x: auto;" class="">
          <table class="w-full min-w-[1100px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Akun Beban</th>
                <th class="px-4 py-3">Aset Terkait</th>
                <th class="px-4 py-3">Keterangan Detail</th>
                <th class="px-4 py-3">Nominal</th>
              </tr>
            </thead>
            <tbody>
              @for($i = 0; $i < 8; $i++)
                @php $row = $detailRows->get($i, []); @endphp
                <tr class="border-t border-slate-100">
                  <td class="px-4 py-3">
                    <select name="details[{{ $i }}][akun_id]" class="beban-input kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="">Pilih akun Beban</option>
                      @foreach($akunOptions as $akun)
                        <option value="{{ $akun->id }}" {{ (string) ($row['akun_id'] ?? '') === (string) $akun->id ? 'selected' : '' }}>{{ $akun->kode_akun }} - {{ $akun->nama_akun }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td class="px-4 py-3">
                    <select name="details[{{ $i }}][aset_koperasi_id]" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="">Tidak terkait aset</option>
                      @foreach($asetOptions as $aset)
                        @php $identitas = $aset->mobil?->plat_nomor ?? $aset->printer?->nomor_seri ?? '-'; @endphp
                        <option value="{{ $aset->id }}" {{ (string) ($row['aset_koperasi_id'] ?? '') === (string) $aset->id ? 'selected' : '' }}>{{ $aset->kode_aset }} - {{ strtoupper($aset->jenis_aset) }} - {{ $identitas }} - {{ $aset->merek }} {{ $aset->model }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td class="px-4 py-3">
                    <input name="details[{{ $i }}][keterangan]" value="{{ $row['keterangan'] ?? '' }}" placeholder="Mis. servis printer / ATK / BBM" class="kbsm-focus w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                  </td>
                  <td class="px-4 py-3">
                    <input type="number" min="1" name="details[{{ $i }}][nominal]" value="{{ $row['nominal'] ?? '' }}" class="beban-nominal kbsm-focus w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="250000">
                  </td>
                </tr>
              @endfor
            </tbody>
          </table>
        </div>
        <div class="border-t border-slate-100 bg-slate-50 px-4 py-4 text-sm">
          Total draft: <span id="beban-total" class="font-bold text-[#073b5c]">Rp 0</span>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <button class="rounded-xl bg-[#073b5c] px-5 py-3 text-xs font-bold uppercase text-white shadow-lg">{{ $editData ? 'Simpan Draft' : 'Buat Draft' }}</button>
        @if($editData)
          <a href="{{ route('beban-operasional.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-xs font-bold uppercase text-slate-600">Batal Edit</a>
        @endif
      </div>
    </form>
  </section>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <form method="GET" action="{{ route('beban-operasional.index') }}" class="grid gap-4 md:grid-cols-6">
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
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Dompet</label>
        <select name="dompet_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($dompetOptions as $dompet)
            <option value="{{ $dompet->id }}" {{ (string) request('dompet_id') === (string) $dompet->id ? 'selected' : '' }}>{{ $dompet->nama_dompet }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Akun</label>
        <select name="akun_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($akunOptions as $akun)
            <option value="{{ $akun->id }}" {{ (string) request('akun_id') === (string) $akun->id ? 'selected' : '' }}>{{ $akun->kode_akun }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Dari</label>
        <input type="date" name="tanggal_dari" value="{{ request('tanggal_dari') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Sampai</label>
        <input type="date" name="tanggal_sampai" value="{{ request('tanggal_sampai') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div class="flex items-end">
        <button class="w-full rounded-xl bg-[#073b5c] px-5 py-3 text-xs font-bold uppercase text-white shadow-lg">Filter</button>
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h2 class="font-bold text-slate-700">Daftar Beban Operasional</h2>
      <p class="text-sm text-slate-400">Draft dapat diedit/dibatalkan. Posted tidak dapat diedit/hapus; gunakan reversal penuh.</p>
    </div>
    <div style="overflow-x: auto;" class="">
      <table class="w-full min-w-[1600px] text-left text-sm">
        <thead class="bg-[#073b5c] text-xs uppercase text-white">
          <tr>
            <th class="px-6 py-4">Kode/Tanggal</th>
            <th class="px-6 py-4">Detail</th>
            <th class="px-6 py-4">Dompet</th>
            <th class="px-6 py-4">Total</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4">Posting</th>
            <th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($bebanOperasional as $item)
            <tr class="align-top hover:bg-slate-50">
              <td class="px-6 py-4">
                <div class="font-bold text-[#073b5c]">{{ $item->kode_beban }}</div>
                <div class="text-xs text-slate-400">{{ $item->tanggal_beban->format('d/m/Y') }}</div>
                @if($item->keterangan)<div class="mt-2 text-xs text-slate-500">{{ $item->keterangan }}</div>@endif
              </td>
              <td class="px-6 py-4">
                <div class="mb-1 text-xs font-bold text-slate-500">{{ $item->details->count() }} baris detail</div>
                <ul class="space-y-1 text-xs text-slate-500">
                  @foreach($item->details as $detail)
                    <li>
                      <span class="font-semibold text-slate-700">{{ $detail->kode_akun_snapshot ?: $detail->akun?->kode_akun }}</span>
                      / {{ $detail->nama_akun_snapshot ?: $detail->akun?->nama_akun }}
                      - Rp {{ number_format((float) $detail->nominal, 0, ',', '.') }}
                      @if($detail->kode_aset_snapshot || $detail->aset)
                        <span class="text-green-700">({{ $detail->kode_aset_snapshot ?: $detail->aset?->kode_aset }})</span>
                      @endif
                      <div>{{ $detail->keterangan }}</div>
                    </li>
                  @endforeach
                </ul>
              </td>
              <td class="px-6 py-4 text-xs text-slate-500">
                @if($item->dompet)
                  <div class="font-semibold text-slate-700">{{ $item->dompet->nama_dompet }}</div>
                  <div>{{ $item->metode_pembayaran }} / {{ $item->dompet->akun?->kode_akun }} {{ $item->dompet->akun?->nama_akun }}</div>
                @else
                  Belum dipilih
                @endif
              </td>
              <td class="px-6 py-4 font-bold text-[#073b5c]">Rp {{ number_format((float) $item->total_beban, 0, ',', '.') }}</td>
              <td class="px-6 py-4">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge($item->status) }}">{{ $item->status_label }}</span>
                @if($item->alasan_reversal)
                  <div class="mt-2 text-xs text-slate-500">Alasan: {{ $item->alasan_reversal }}</div>
                @endif
              </td>
              <td class="px-6 py-4 text-xs text-slate-500">
                <div>Mutasi: {{ $item->mutasiKas->count() }}</div>
                <div>Jurnal: {{ $item->jurnal->count() }}</div>
                <div>Posted: {{ $item->posted_at?->format('d/m/Y H:i') ?? '-' }}</div>
                <div>Reversal: {{ $item->reversal?->kode_reversal ?? '-' }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex max-w-[520px] flex-col gap-2">
                  @if($item->status === 'draft')
                    <div class="flex flex-wrap gap-2">
                      <a href="{{ route('beban-operasional.edit', $item) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Edit Draft</a>
                      <form method="POST" action="{{ route('beban-operasional.cancel-draft', $item) }}" onsubmit="return confirm('Batalkan draft ini?')">
                        @csrf
                        <button class="rounded-lg border border-red-300 px-3 py-2 text-xs font-bold text-red-700">Cancel Draft</button>
                      </form>
                    </div>
                    <form method="POST" action="{{ route('beban-operasional.post', $item) }}" class="flex gap-2" onsubmit="return confirm('Posting Beban Operasional ini? Saldo Dompet akan berkurang dan Jurnal akan dibuat.')">
                      @csrf
                      <select name="dompet_id" required class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="">Pilih Dompet</option>
                        @foreach($dompetOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }}) - Saldo Rp {{ number_format((float) $dompet->saldo, 0, ',', '.') }}</option>
                        @endforeach
                      </select>
                      <button class="rounded-lg bg-[#2f8f3a] px-3 py-2 text-xs font-bold text-white">Posting</button>
                    </form>
                  @elseif($item->status === 'posted')
                    <form method="POST" action="{{ route('beban-operasional.reverse', $item) }}" class="flex gap-2" onsubmit="return confirm('Reversal penuh Beban Operasional ini? Proses tidak menghapus transaksi asli.')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan reversal penuh" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold text-amber-700">Reversal</button>
                    </form>
                  @else
                    <span class="text-xs text-slate-400">Final - tidak ada aksi edit/hapus.</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada Beban Operasional.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $bebanOperasional->links() }}</div>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
    const refresh = () => {
      let total = 0;
      document.querySelectorAll('.beban-nominal').forEach((input) => {
        total += parseInt(input.value || '0', 10) || 0;
      });
      const target = document.getElementById('beban-total');
      if (target) {
        target.textContent = rupiah(total);
      }
    };
    document.querySelectorAll('.beban-nominal').forEach((input) => input.addEventListener('input', refresh));
    refresh();
  });
</script>
@endsection
