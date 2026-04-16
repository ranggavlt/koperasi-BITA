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

  {{-- CARD 1: FORM --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl flex justify-between items-center">
          <div>
            <h6>Tambah Penjualan Kasbon</h6>
            <p class="text-sm text-slate-400">Saldo Maksimal Rp 2.000.000 / Karyawan / Bulan</p>
          </div>
          
          <button type="button" onclick="toggleForm()" id="btn-toggle-form"
            class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
            {{ $errors->any() ? 'Tutup Form' : '+ Tambah Transaksi' }}
          </button>
        </div>

        <div id="form-container" class="flex-auto p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
          <form action="{{ route('penjualan.store') }}" method="POST">
            @csrf

            <div class="flex flex-wrap -mx-3">
              
              {{-- Kode Transaksi --}}
              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Kode Transaksi</label>
                <input type="text" readonly value="Otomatis"
                  class="text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-gray-100 px-3 py-2 font-bold text-gray-600 cursor-not-allowed">
              </div>

              {{-- Karyawan & Sisa Saldo --}}
              <div class="w-full max-w-full px-3 md:w-4/12 mt-4 md:mt-0">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Pilih Karyawan</label>
                <select name="karyawan_id" required
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">-- Pilih Karyawan --</option>
                  @foreach($karyawan as $item)
                    @if($item->sisa_limit <= 0)
                      <option value="{{ $item->id }}" disabled class="text-red-500 font-semibold bg-red-50">
                        {{ $item->nama }} (Sisa Saldo Habis!)
                      </option>
                    @else
                      <option value="{{ $item->id }}" {{ old('karyawan_id') == $item->id ? 'selected' : '' }}>
                        {{ $item->nama }} - Sisa: Rp {{ number_format($item->sisa_limit, 0, ',', '.') }}
                      </option>
                    @endif
                  @endforeach
                </select>
              </div>

              {{-- Multi Produk --}}
              <div class="w-full max-w-full px-3 mt-4">
                <div class="mb-2 flex items-center justify-between">
                  <label class="mb-0 ml-1 block text-xs font-bold uppercase text-slate-700">Produk & Jumlah</label>
                  <button type="button" id="btn-add-item"
                    class="inline-flex items-center rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-3 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                    + Tambah Produk
                  </button>
                </div>

                <div id="items-container" class="space-y-3"></div>
                <p class="mt-2 text-xs text-slate-500">Klik tombol + untuk menambah produk lain dalam transaksi yang sama.</p>
              </div>

              {{-- Total Harga --}}
              <div class="w-full max-w-full px-3 md:w-3/12 mt-4">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Total Harga</label>
                <input type="number" name="total_harga" id="total_harga" readonly value="0"
                  class="text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-gray-100 px-3 py-2 font-bold text-gray-700 cursor-not-allowed">
              </div>

              {{-- Diskon --}}
              <div class="w-full max-w-full px-3 md:w-3/12 mt-4">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Diskon (Rp)</label>
                <input type="number" name="diskon" id="diskon" oninput="kalkulasiOtomatis()"
                  value="{{ old('diskon', 0) }}" min="0"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              {{-- Grand Total --}}
              <div class="w-full max-w-full px-3 md:w-3/12 mt-4">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700 text-green-600">Grand Total</label>
                <input type="number" name="grand_total" id="grand_total" readonly value="0"
                  class="text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-green-500 bg-green-50 px-3 py-2 font-bold text-green-700 cursor-not-allowed">
              </div>

            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                Simpan Transaksi
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- CARD 2: TABEL --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Riwayat Transaksi Penjualan</h6>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Kode & Tgl</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Karyawan</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Barang & Qty</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Rincian Harga</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Aksi</th>
                </tr>
              </thead>

              <tbody>
                @forelse($penjualan as $item)
                  <tr>
                    {{-- KODE TRANSAKSI --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $penjualan->firstItem() + $loop->index }}
                        </div>
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm font-bold text-slate-700">{{ $item->kode_transaksi }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            {{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    {{-- KARYAWAN --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <p class="mb-0 px-4 text-sm font-semibold leading-tight text-slate-600">
                        {{ $item->karyawan->nama ?? 'Tidak Diketahui' }}
                      </p>
                    </td>

                    {{-- PRODUK & JUMLAH --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="px-4">
                        @foreach($item->details as $detail)
                          <p class="mb-0 text-sm font-semibold text-blue-600">
                            {{ $detail->produk->nama_produk ?? 'Produk Dihapus' }} 
                            <span class="text-slate-500">x {{ $detail->qty }}</span>
                          </p>
                        @endforeach
                      </div>
                    </td>

                    {{-- RINCIAN HARGA --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-center">
                      <p class="mb-0 text-xs text-slate-500">Harga: Rp {{ number_format($item->total_harga, 0, ',', '.') }}</p>
                      <p class="mb-0 text-xs text-red-500">Diskon: Rp {{ number_format($item->diskon, 0, ',', '.') }}</p>
                      <p class="mb-0 text-sm font-bold text-green-600 mt-1">Total: Rp {{ number_format($item->grand_total, 0, ',', '.') }}</p>
                    </td>

                    {{-- AKSI --}}
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <form method="POST" action="{{ route('penjualan.destroy', $item->id) }}"
                            onsubmit="return confirm('Hapus transaksi ini? Stok akan dikembalikan.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                          class="inline-block rounded-lg bg-gradient-to-tl from-red-600 to-rose-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                          Batalkan
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="p-6 text-center text-sm text-slate-400">
                      Belum ada data transaksi.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="p-4 border-t border-gray-200">
            {{ $penjualan->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- SCRIPT JAVASCRIPT --}}
<script>
  const produkData = @json($produkOptions);
  const oldItems = @json(old('items', []));

  function toggleForm() {
    const formContainer = document.getElementById('form-container');
    const btnToggle = document.getElementById('btn-toggle-form');

    if (formContainer.classList.contains('hidden')) {
      formContainer.classList.remove('hidden');
      formContainer.classList.add('block');
      btnToggle.innerHTML = 'Tutup Form';
    } else {
      formContainer.classList.add('hidden');
      formContainer.classList.remove('block');
      btnToggle.innerHTML = '+ Tambah Transaksi';
    }
  }

  function buildProdukOptions(selectedId = '') {
    const options = ['<option value="">-- Pilih Produk --</option>'];
    produkData.forEach((p) => {
      const selected = String(selectedId) === String(p.id) ? 'selected' : '';
      options.push(
        `<option value="${p.id}" data-harga="${p.harga_jual}" ${selected}>${p.nama_produk} (Sisa: ${p.stok} | Rp ${Number(p.harga_jual).toLocaleString('id-ID')})</option>`
      );
    });
    return options.join('');
  }

  function createItemRow(index, selectedProduk = '', jumlah = 1) {
    const row = document.createElement('div');
    row.className = 'item-row flex flex-wrap -mx-2 rounded-lg border border-gray-200 bg-gray-50 p-3';
    row.innerHTML = `
      <div class="w-full max-w-full px-2 md:w-7/12">
        <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Produk</label>
        <select name="items[${index}][produk_id]" required class="produk-select focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
          ${buildProdukOptions(selectedProduk)}
        </select>
      </div>
      <div class="w-full max-w-full px-2 md:w-3/12 mt-3 md:mt-0">
        <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jumlah (Qty)</label>
        <input type="number" name="items[${index}][jumlah]" required min="1" value="${jumlah}" class="jumlah-input focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
      </div>
      <div class="w-full max-w-full px-2 md:w-2/12 mt-3 md:mt-0 flex items-end">
        <button type="button" class="btn-remove-item inline-block w-full rounded-lg bg-gradient-to-tl from-red-600 to-rose-400 px-3 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
          Hapus
        </button>
      </div>
    `;
    return row;
  }

  function reindexRows() {
    const rows = document.querySelectorAll('#items-container .item-row');
    rows.forEach((row, index) => {
      const select = row.querySelector('.produk-select');
      const qty = row.querySelector('.jumlah-input');
      select.name = `items[${index}][produk_id]`;
      qty.name = `items[${index}][jumlah]`;
    });
  }

  function addRow(selectedProduk = '', jumlah = 1) {
    const container = document.getElementById('items-container');
    const index = container.querySelectorAll('.item-row').length;
    const row = createItemRow(index, selectedProduk, jumlah);
    container.appendChild(row);

    row.querySelector('.produk-select').addEventListener('change', kalkulasiOtomatis);
    row.querySelector('.jumlah-input').addEventListener('input', kalkulasiOtomatis);
    row.querySelector('.btn-remove-item').addEventListener('click', function () {
      row.remove();
      if (container.querySelectorAll('.item-row').length === 0) {
        addRow();
      }
      reindexRows();
      kalkulasiOtomatis();
    });

    kalkulasiOtomatis();
  }

  function kalkulasiOtomatis() {
    const rows = document.querySelectorAll('#items-container .item-row');
    let totalHarga = 0;

    rows.forEach((row) => {
      const select = row.querySelector('.produk-select');
      const qty = parseFloat(row.querySelector('.jumlah-input').value) || 0;
      const harga = parseFloat(select.options[select.selectedIndex]?.getAttribute('data-harga')) || 0;
      totalHarga += harga * qty;
    });

    const diskon = parseFloat(document.getElementById('diskon').value) || 0;
    let grandTotal = totalHarga - diskon;
    if (grandTotal < 0) grandTotal = 0;

    document.getElementById('total_harga').value = totalHarga;
    document.getElementById('grand_total').value = grandTotal;
  }

  document.getElementById('btn-add-item').addEventListener('click', function () {
    addRow();
  });

  document.getElementById('diskon').addEventListener('input', kalkulasiOtomatis);

  if (Array.isArray(oldItems) && oldItems.length > 0) {
    oldItems.forEach((item) => {
      addRow(item.produk_id ?? '', item.jumlah ?? 1);
    });
  } else {
    addRow();
  }
</script>
@endsection
