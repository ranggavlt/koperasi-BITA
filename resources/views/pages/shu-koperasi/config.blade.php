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

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-700 m-0">Pengaturan Persentase SHU</h2>
      <p class="mt-1 text-sm text-slate-400">Setiap simpan membuat versi audit baru. Periode SHU lama tidak pernah ikut berubah.</p>
    </div>
    @if(config('features.shu_enabled'))
      <a href="{{ route('shu-koperasi.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold uppercase text-slate-600 hover:bg-slate-50 transition-all">Kembali ke Daftar SHU</a>
    @else
      <span class="rounded-lg bg-amber-100 px-4 py-2 text-xs font-bold uppercase text-amber-700">Operasional SHU masih nonaktif</span>
    @endif
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative mb-6 flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="flex-auto p-6">
          <form action="{{ route('shu-config.update') }}" method="POST" data-shu-config-form>
            @csrf

            <div class="mb-6 grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Berlaku Mulai</label>
                <input type="date" name="berlaku_mulai" required value="{{ old('berlaku_mulai', now()->toDateString()) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                <p class="mt-1 text-xs text-slate-400">Periode dengan tanggal mulai pada/ setelah tanggal ini memakai versi tersebut.</p>
              </div>
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dasar/Alasan Pengaturan</label>
                <textarea name="dasar_persetujuan" required minlength="5" maxlength="1000" rows="2"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Contoh: Keputusan RAT tahun buku 2026">{{ old('dasar_persetujuan') }}</textarea>
              </div>
            </div>

            <h3 class="mb-4 text-base font-bold text-slate-700">Pembagian SHU Total (100%)</h3>
            <p class="mb-4 text-sm text-slate-500">Total saat ini: <strong data-shu-total>0,00%</strong></p>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dana Cadangan (%)</label>
                <input type="number" name="persen_dana_cadangan" min="0" max="100" step="0.01" value="{{ old('persen_dana_cadangan', (float) $shuConfig->persen_dana_cadangan) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">SHU Anggota (%)</label>
                <input type="number" name="persen_anggota" min="0" max="100" step="0.01" value="{{ old('persen_anggota', (float) $shuConfig->persen_anggota) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Pengurus (%)</label>
                <input type="number" name="persen_pengurus" min="0" max="100" step="0.01" value="{{ old('persen_pengurus', (float) $shuConfig->persen_pengurus) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dana Sosial (%)</label>
                <input type="number" name="persen_dana_sosial" min="0" max="100" step="0.01" value="{{ old('persen_dana_sosial', (float) $shuConfig->persen_dana_sosial) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dana Pendidikan (%)</label>
                <input type="number" name="persen_dana_pendidikan" min="0" max="100" step="0.01" value="{{ old('persen_dana_pendidikan', (float) $shuConfig->persen_dana_pendidikan) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Pembina (%)</label>
                <input type="number" name="persen_pembina" min="0" max="100" step="0.01" value="{{ old('persen_pembina', (float) $shuConfig->persen_pembina) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Pengawas (%)</label>
                <input type="number" name="persen_pengawas" min="0" max="100" step="0.01" value="{{ old('persen_pengawas', (float) $shuConfig->persen_pengawas) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>
            </div>

            <hr class="my-6 border-slate-200">

            <h3 class="mb-4 text-base font-bold text-slate-700">Porsi Jasa dari SHU Anggota (Total 100%)</h3>
            <p class="mb-4 text-sm text-slate-500">Total jasa saat ini: <strong data-shu-member-total>0,00%</strong></p>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Jasa Modal (dari Simpanan) (%)</label>
                <input type="number" name="persen_jasa_modal" min="0" max="100" step="0.01" value="{{ old('persen_jasa_modal', (float) $shuConfig->persen_jasa_modal) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Jasa Usaha (dari Pembelian Waserba) (%)</label>
                <input type="number" name="persen_jasa_usaha" min="0" max="100" step="0.01" value="{{ old('persen_jasa_usaha', (float) $shuConfig->persen_jasa_usaha) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>
            </div>

            <div class="mt-6 flex gap-3">
              <button type="submit" class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832] transition-all">
                Simpan Pengaturan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="rounded-2xl bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h3 class="text-base font-bold text-slate-700">Histori Versi Konfigurasi</h3>
      <p class="mt-1 text-sm text-slate-400">Histori tidak dapat diedit atau dihapus. Versi paling baru yang sudah berlaku dipakai untuk periode baru.</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500">
          <tr><th class="px-5 py-3">Berlaku</th><th class="px-5 py-3">Pembagian</th><th class="px-5 py-3">Jasa Anggota</th><th class="px-5 py-3">Dasar</th><th class="px-5 py-3">Disimpan Oleh</th></tr>
        </thead>
        <tbody>
          @forelse($configHistory as $item)
            <tr class="border-t border-slate-100 align-top">
              <td class="px-5 py-4 font-semibold">{{ $item->berlaku_mulai?->format('d/m/Y') ?? '-' }}</td>
              <td class="px-5 py-4 text-xs leading-5">Cadangan {{ $item->persen_dana_cadangan }}%; Anggota {{ $item->persen_anggota }}%; Pengurus {{ $item->persen_pengurus }}%; Pengawas {{ $item->persen_pengawas }}%; Pembina {{ $item->persen_pembina }}%; Sosial {{ $item->persen_dana_sosial }}%; Pendidikan {{ $item->persen_dana_pendidikan }}%</td>
              <td class="px-5 py-4 text-xs">Modal {{ $item->persen_jasa_modal }}%; Usaha {{ $item->persen_jasa_usaha }}%</td>
              <td class="px-5 py-4">{{ $item->dasar_persetujuan }}</td>
              <td class="px-5 py-4 text-xs">{{ $item->approver?->name ?? '-' }}<br>{{ $item->approved_at?->format('d/m/Y H:i') ?? '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Belum ada konfigurasi. Isi form di atas sebelum membuat periode SHU.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $configHistory->links() }}</div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-shu-config-form]');
    if (!form) return;
    const allocationNames = ['persen_dana_cadangan', 'persen_anggota', 'persen_pengawas', 'persen_pembina', 'persen_pengurus', 'persen_dana_sosial', 'persen_dana_pendidikan'];
    const memberNames = ['persen_jasa_modal', 'persen_jasa_usaha'];
    const sum = (names) => names.reduce((total, name) => total + (Number(form.elements[name]?.value) || 0), 0);
    const refresh = () => {
      form.querySelector('[data-shu-total]').textContent = `${sum(allocationNames).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%`;
      form.querySelector('[data-shu-member-total]').textContent = `${sum(memberNames).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%`;
    };
    form.addEventListener('input', refresh);
    refresh();
  });
</script>
@endsection
