@extends('layout.app')
@section('content')
<div class="flex flex-wrap -mx-3">
  <div class="flex-none w-full max-w-full px-3">
    
    @if(session('success'))
      <div class="relative p-4 mb-4 text-white border border-solid rounded-lg bg-gradient-to-tl from-emerald-500 to-teal-400 border-emerald-300">
        {{ session('success') }}
      </div>
    @endif
    @if(session('error'))
      <div class="relative p-4 mb-4 text-white border border-solid rounded-lg bg-gradient-to-tl from-red-600 to-orange-600 border-red-300">
        {{ session('error') }}
      </div>
    @endif
    @if ($errors->any())
        <div class="relative p-4 mb-4 text-white border border-solid rounded-lg bg-gradient-to-tl from-red-600 to-orange-600 border-red-300">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 border-transparent border-solid shadow-soft-xl rounded-2xl bg-clip-border">
      <div class="p-6 pb-0 mb-0 bg-white border-b-0 border-b-solid rounded-t-2xl border-b-transparent">
        <h6 class="mb-1">Pencairan Dana Sosial & Sumbangan</h6>
        <p class="text-sm leading-normal text-slate-500 mb-0">Catat setiap pengeluaran dana tak terduga (misal: santunan kematian, pernikahan, sumbangan proposal).</p>
      </div>
      
      <div class="flex-auto px-6 pt-4 pb-6">
        <form action="{{ route('klaim-dana-khusus.store') }}" method="POST">
          @csrf
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <div class="mb-4">
              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Pilih Dompet / Kas</label>
              <select name="dompet_id" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none" required>
                @foreach($dompets as $dompet)
                  <option value="{{ $dompet->id }}">
                    {{ $dompet->nama_dompet }} (Sosial: Rp {{ number_format($dompet->saldo_dana_sosial,0,',','.') }}, Sumbangan: Rp {{ number_format($dompet->saldo_sumbangan_anggota,0,',','.') }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-4">
              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Jenis Dana</label>
              <select name="jenis_dana" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none" required>
                <option value="sosial">Dana Sosial</option>
                <option value="sumbangan">Dana Sumbangan Anggota</option>
              </select>
            </div>

            <div class="mb-4">
              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Kategori / Alasan Pencairan</label>
              <select name="kategori" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none" required>
                <option value="meninggal">Keluarga Meninggal</option>
                <option value="melahirkan">Istri Melahirkan</option>
                <option value="khitan_menikah">Anak Dikhitan / Menikah</option>
                <option value="proposal">Sumbangan Proposal</option>
                <option value="lainnya">Keperluan Lainnya</option>
              </select>
            </div>

            <div class="mb-4">
              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Nominal Pencairan</label>
              <input type="number" name="nominal" min="1" step="1" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none" required>
            </div>

            <div class="mb-4">
              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Tanggal Transaksi</label>
              <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none" required>
            </div>

            <div class="mb-4 md:col-span-2">
              <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Keterangan / Rincian</label>
              <textarea name="keterangan" rows="2" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none" placeholder="Misal: Santunan kematian Bpk. Budi, klaim sumbangan sunatan anak Bpk. Andi, dll" required></textarea>
            </div>

          </div>
          <div class="mt-2 text-right">
            <button type="submit" class="inline-block px-6 py-3 font-bold text-center text-white uppercase align-middle transition-all rounded-lg cursor-pointer bg-gradient-to-tl from-purple-700 to-pink-500 leading-pro text-xs ease-soft-in tracking-tight-soft shadow-soft-md bg-150 bg-x-25 hover:scale-102 active:opacity-85 hover:shadow-soft-xs">
              Proses Pencairan & Buat Jurnal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel Riwayat Pencairan -->
    <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 border-transparent border-solid shadow-soft-xl rounded-2xl bg-clip-border">
      <div class="p-6 pb-0 mb-0 bg-white border-b-0 border-b-solid rounded-t-2xl border-b-transparent">
        <h6 class="mb-0">Riwayat Pencairan Dana Khusus</h6>
      </div>
      <div class="flex-auto px-0 pt-0 pb-2">
        <div class="p-0 overflow-x-auto">
          <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
            <thead class="align-bottom">
              <tr>
                <th class="px-6 py-3 font-bold text-left uppercase align-middle bg-transparent border-b border-gray-200 shadow-none text-xxs border-b-solid tracking-none whitespace-nowrap text-slate-400 opacity-70">Waktu & User</th>
                <th class="px-6 py-3 font-bold text-left uppercase align-middle bg-transparent border-b border-gray-200 shadow-none text-xxs border-b-solid tracking-none whitespace-nowrap text-slate-400 opacity-70">Detail Kas & Jenis</th>
                <th class="px-6 py-3 font-bold text-left uppercase align-middle bg-transparent border-b border-gray-200 shadow-none text-xxs border-b-solid tracking-none whitespace-nowrap text-slate-400 opacity-70">Kategori & Keterangan</th>
                <th class="px-6 py-3 font-bold text-center uppercase align-middle bg-transparent border-b border-gray-200 shadow-none text-xxs border-b-solid tracking-none whitespace-nowrap text-slate-400 opacity-70">Nominal</th>
              </tr>
            </thead>
            <tbody>
              @forelse($klaims as $klaim)
              <tr>
                <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                  <div class="flex px-2 py-1">
                    <div class="flex flex-col justify-center">
                      <h6 class="mb-0 text-sm leading-normal">{{ $klaim->tanggal->format('d/m/Y') }}</h6>
                      <p class="mb-0 text-xs leading-tight text-slate-400">By: {{ $klaim->creator->name ?? 'System' }}</p>
                    </div>
                  </div>
                </td>
                <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                  <p class="mb-0 text-xs font-semibold leading-tight text-slate-600">{{ $klaim->dompet->nama_dompet ?? '-' }}</p>
                  <p class="mb-0 text-xs leading-tight text-slate-400">
                    <span class="inline-block rounded bg-indigo-100 px-2 py-1 text-xs font-bold text-indigo-700">
                      {{ strtoupper($klaim->jenis_dana) }}
                    </span>
                  </p>
                </td>
                <td class="p-2 align-middle bg-transparent border-b whitespace-normal shadow-transparent" style="max-width: 250px;">
                  <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ strtoupper(str_replace('_', ' ', $klaim->kategori)) }}</h6>
                  <p class="mb-0 text-xs leading-tight text-slate-500 whitespace-normal break-words">{{ $klaim->keterangan }}</p>
                </td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                  <span class="text-xs font-semibold leading-tight text-red-500">- Rp {{ number_format($klaim->nominal, 0, ',', '.') }}</span>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="p-4 text-center text-sm text-slate-500">Belum ada riwayat pencairan dana khusus.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
          <div class="px-6 py-4">
            {{ $klaims->links() }}
          </div>
        </div>
      </div>
    </div>
    
  </div>
</div>
@endsection
