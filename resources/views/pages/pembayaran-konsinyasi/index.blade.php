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

  @if (! $hasDompet)
    <div class="mb-4 rounded-lg bg-yellow-100 px-4 py-3 text-sm text-yellow-800">
      Pembayaran konsinyasi belum bisa dicatat otomatis ke mutasi kas karena data dompet koperasi belum tersedia.
    </div>
  @endif

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h6>Pembayaran Konsinyasi</h6>
              <p class="text-sm text-slate-400">
                Pilih reseller untuk melihat sisa stok, barang yang laku, dan bayarkan otomatis ke mutasi kas.
              </p>
            </div>

            @if ($selectedReseller)
              <div class="rounded-xl px-4 py-2 text-xs font-bold uppercase text-slate-500" style="background-color: #f8fafc;">
                Reseller aktif: {{ $selectedReseller->nama_reseller }}
              </div>
            @endif
          </div>
        </div>

        <div class="flex-auto p-6">
          <form method="GET" action="{{ route('pembayaran-konsinyasi.index') }}">
            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-8/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Pilih Reseller
                </label>
                <select name="reseller_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  @forelse($reseller as $item)
                    <option value="{{ $item->id }}" {{ (string) $selectedResellerId === (string) $item->id ? 'selected' : '' }}>
                      {{ $item->nama_reseller }}
                    </option>
                  @empty
                    <option value="">Belum ada reseller konsinyasi</option>
                  @endforelse
                </select>
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700 opacity-0">
                  Aksi
                </label>
                <button type="submit"
                  class="inline-block w-full rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                  Tampilkan Ringkasan
                </button>
              </div>
            </div>
          </form>

          @if ($selectedReseller)
            <div class="mt-6 flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 mb-4" style="flex: 1 1 220px;">
                <div class="relative flex flex-col min-w-0 break-words rounded-2xl bg-white shadow-soft-xl bg-clip-border">
                  <div class="flex-auto p-4">
                    <p class="mb-1 text-xs font-bold uppercase text-slate-400">Sisa Stok</p>
                    <h5 class="mb-0 font-bold text-slate-700">{{ number_format($totalStokTersisa, 0, ',', '.') }}</h5>
                  </div>
                </div>
              </div>

              <div class="w-full max-w-full px-3 mb-4" style="flex: 1 1 220px;">
                <div class="relative flex flex-col min-w-0 break-words rounded-2xl bg-white shadow-soft-xl bg-clip-border">
                  <div class="flex-auto p-4">
                    <p class="mb-1 text-xs font-bold uppercase text-slate-400">Laku Belum Dibayar</p>
                    <h5 class="mb-0 font-bold text-slate-700">{{ number_format($ringkasan['total_qty'], 0, ',', '.') }}</h5>
                  </div>
                </div>
              </div>

              <div class="w-full max-w-full px-3 mb-4" style="flex: 1 1 220px;">
                <div class="relative flex flex-col min-w-0 break-words rounded-2xl bg-white shadow-soft-xl bg-clip-border">
                  <div class="flex-auto p-4">
                    <p class="mb-1 text-xs font-bold uppercase text-slate-400">Nilai Jual</p>
                    <h5 class="mb-0 font-bold text-slate-700">Rp {{ number_format($ringkasan['total_jual'], 0, ',', '.') }}</h5>
                  </div>
                </div>
              </div>

              <div class="w-full max-w-full px-3 mb-4" style="flex: 1 1 220px;">
                <div class="relative flex flex-col min-w-0 break-words rounded-2xl bg-white shadow-soft-xl bg-clip-border">
                  <div class="flex-auto p-4">
                    <p class="mb-1 text-xs font-bold uppercase text-slate-400">Bayar ke Reseller</p>
                    <h5 class="mb-0 font-bold text-slate-700">Rp {{ number_format($ringkasan['total_bayar'], 0, ',', '.') }}</h5>
                  </div>
                </div>
              </div>

              <div class="w-full max-w-full px-3 mb-4" style="flex: 1 1 220px;">
                <div class="relative flex flex-col min-w-0 break-words rounded-2xl bg-white shadow-soft-xl bg-clip-border">
                  <div class="flex-auto p-4">
                    <p class="mb-1 text-xs font-bold uppercase text-slate-400">Margin Koperasi</p>
                    <h5 class="mb-0 font-bold text-slate-700">Rp {{ number_format($ringkasan['total_margin'], 0, ',', '.') }}</h5>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-2 rounded-2xl p-4" style="background-color: #f8fafc;">
              <div class="mb-4">
                <h6 class="mb-1">Bayar Tagihan Konsinyasi</h6>
                <p class="text-sm text-slate-400 mb-0">
                  Sistem akan membayar seluruh transaksi konsinyasi reseller yang statusnya masih belum dibayar.
                </p>
              </div>

              @if ($ringkasan['baris_hutang'] > 0)
                <form action="{{ route('pembayaran-konsinyasi.store') }}" method="POST">
                  @csrf
                  <input type="hidden" name="reseller_id" value="{{ $selectedReseller->id }}">

                  <div class="flex flex-wrap -mx-3">
                    <div class="w-full max-w-full px-3 md:w-4/12">
                      <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                        Dompet Koperasi
                      </label>
                      <select name="dompet_id"
                        class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                        {{ $hasDompet ? '' : 'disabled' }}>
                        <option value="">-- Pilih Dompet --</option>
                        @foreach($dompet as $item)
                          <option value="{{ $item->id }}" {{ old('dompet_id') == $item->id ? 'selected' : '' }}>
                            {{ $item->nama_dompet }} (Saldo: Rp {{ number_format($item->saldo, 0, ',', '.') }})
                          </option>
                        @endforeach
                      </select>
                    </div>

                    <div class="w-full max-w-full px-3 md:w-3/12">
                      <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                        Tanggal Bayar
                      </label>
                      <input type="date" name="tanggal_bayar"
                        value="{{ old('tanggal_bayar', now()->toDateString()) }}"
                        class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                        {{ $hasDompet ? '' : 'disabled' }}>
                    </div>

                    <div class="w-full max-w-full px-3 md:w-5/12">
                      <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                        Keterangan
                      </label>
                      <input type="text" name="keterangan"
                        value="{{ old('keterangan', 'Pembayaran konsinyasi reseller ' . $selectedReseller->nama_reseller) }}"
                        class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                        placeholder="Catatan pembayaran"
                        {{ $hasDompet ? '' : 'disabled' }}>
                    </div>
                  </div>

                  <div class="mt-6 flex flex-wrap gap-2">
                    <button type="submit"
                      class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all"
                      style="{{ $hasDompet ? '' : 'opacity: 0.5; cursor: not-allowed;' }}"
                      {{ $hasDompet ? '' : 'disabled' }}>
                      Bayar Rp {{ number_format($ringkasan['total_bayar'], 0, ',', '.') }}
                    </button>

                    <span class="inline-flex items-center rounded-lg bg-white px-4 py-3 text-xs font-bold uppercase text-slate-500 shadow-soft-xs">
                      {{ $ringkasan['baris_hutang'] }} transaksi konsinyasi belum dibayar
                    </span>
                  </div>
                </form>
              @else
                <div class="rounded-lg bg-green-100 px-4 py-3 text-sm text-green-700">
                  Semua penjualan konsinyasi untuk reseller ini sudah dibayar.
                </div>
              @endif
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Ringkasan Produk Konsinyasi</h6>
          <p class="text-sm text-slate-400">Sisa stok, barang laku, dan nilai yang masih perlu dibayarkan per produk.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div style="overflow-x: auto;" class="p-0">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Produk</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Sisa Stok</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Laku Belum Dibayar</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Laku Sudah Dibayar</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Harga Setor</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Harga Jual</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Margin / Item</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Bayar Outstanding</th>
                </tr>
              </thead>
              <tbody>
                @forelse($produkRingkasan as $item)
                  @php
                    $marginPerItem = (int) $item->harga_jual - (int) $item->harga_setor;
                  @endphp
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $loop->iteration }}
                        </div>
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ $item->nama_produk }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Total laku: {{ number_format((int) $item->total_laku, 0, ',', '.') }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-slate-600 to-slate-300 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        {{ number_format((int) $item->stok, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if((int) $item->qty_belum_dibayar > 0)
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-yellow-500 to-orange-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          {{ number_format((int) $item->qty_belum_dibayar, 0, ',', '.') }}
                        </span>
                      @else
                        <span class="text-xs font-semibold text-slate-400">0</span>
                      @endif
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if((int) $item->qty_sudah_dibayar > 0)
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          {{ number_format((int) $item->qty_sudah_dibayar, 0, ',', '.') }}
                        </span>
                      @else
                        <span class="text-xs font-semibold text-slate-400">0</span>
                      @endif
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((int) $item->harga_setor, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((int) $item->harga_jual, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format($marginPerItem, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-700">
                        Rp {{ number_format((int) $item->total_bayar_belum_dibayar, 0, ',', '.') }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="p-6 text-center text-sm text-slate-400">
                      Pilih reseller konsinyasi untuk melihat ringkasannya.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Riwayat Pembayaran Konsinyasi</h6>
          <p class="text-sm text-slate-400">Histori pembayaran reseller dan pencatatan pengeluarannya ke mutasi kas.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div style="overflow-x: auto;" class="p-0">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Pembayaran</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Reseller</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Qty</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Nilai Jual</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Bayar</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Margin</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Dompet</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Tanggal</th>
                </tr>
              </thead>
              <tbody>
                @forelse($riwayatPembayaran as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tl from-blue-600 to-cyan-400 text-xs font-bold text-white">
                          {{ $riwayatPembayaran->firstItem() + $loop->index }}
                        </div>
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ $item->kode_pembayaran }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            {{ $item->keterangan ?: 'Pembayaran reseller konsinyasi' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="px-4 text-sm text-slate-600">
                        {{ $item->reseller->nama_reseller ?? '-' }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        {{ number_format((int) $item->total_qty, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((int) $item->total_jual, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-700">
                        Rp {{ number_format((int) $item->total_bayar, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((int) $item->total_margin, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="px-4 text-sm text-slate-600">
                        {{ $item->dompet->nama_dompet ?? '-' }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        {{ optional($item->tanggal_bayar)->format('d/m/Y') ?? '-' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="p-6 text-center text-sm text-slate-400">
                      Belum ada pembayaran konsinyasi.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $riwayatPembayaran->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
