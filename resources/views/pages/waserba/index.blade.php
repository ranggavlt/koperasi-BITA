@extends('layout.main')
@section('title', 'Point of Sale')

@section('content')

<style>
    .pos-wrapper {
        padding: 0 24px 24px 24px;
        width: 100%;
        box-sizing: border-box;
    }
    .pos-layout {
        display: flex;
        flex-direction: column;
        gap: 20px;
        width: 100%;
        box-sizing: border-box;
    }
    .pos-left, .pos-right {
        width: 100%;
        box-sizing: border-box;
    }
    @media (min-width: 1024px) {
        .pos-layout {
            flex-direction: row;
            align-items: flex-start;
        }
        .pos-left {
            flex: 0 0 calc(62% - 10px);
            width: calc(62% - 10px);
        }
        .pos-right {
            flex: 0 0 calc(38% - 10px);
            width: calc(38% - 10px);
        }
    }

    /* Custom Scrollbar for Cart to make it visible and elegant */
    #cartItemsContainer {
        overscroll-behavior: contain; /* Prevent scrolling the main page when reaching the end */
    }
    #cartItemsContainer::-webkit-scrollbar {
        width: 5px;
    }
    #cartItemsContainer::-webkit-scrollbar-track {
        background: transparent;
    }
    #cartItemsContainer::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }
    #cartItemsContainer::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8;
    }
</style>

<div class="pos-wrapper">
    <form id="posForm" action="{{ route('waserba.store') }}" method="POST" style="width: 100%; box-sizing: border-box;">
        @csrf
        <div class="pos-layout">
            <!-- Bagian Produk (KIRI) -->
            <div class="pos-left" style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Search and Filters Container -->
                <div class="flex flex-col gap-3 mb-5 w-full">
                    <!-- Search Bar -->
                    <div class="relative w-full group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="searchInput" class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm" placeholder="Ketik nama produk untuk mencari...">
                    </div>

                    <!-- Filters Row -->
                    <div class="flex gap-3 w-full">
                        <!-- Tipe Filter -->
                        <div class="relative w-1/3">
                            <select onchange="filterTipeDropdown(this.value)" class="w-full appearance-none bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl pl-4 pr-10 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all cursor-pointer shadow-sm">
                                <option value="all">Semua Tipe</option>
                                <option value="0">Koperasi</option>
                                <option value="1">Konsinyasi</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>

                        <!-- Kategori Filter -->
                        <div class="relative w-2/3">
                            <select onchange="filterKategoriDropdown(this.value)" class="w-full appearance-none bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl pl-4 pr-10 py-3 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all cursor-pointer shadow-sm">
                                <option value="all">Semua Kategori</option>
                                @foreach($kategoris as $kategori)
                                    <option value="{{ $kategori->id }}">{{ $kategori->nama_kategori }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid Produk -->
                <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; padding-bottom: 24px; width: 100%;" id="produkGrid">
                    @forelse($produk as $item)
                        <div class="produk-card bg-white cursor-pointer relative flex flex-col group hover:shadow-md transition-shadow" style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);"
                             data-id="{{ $item->id }}"
                             data-kategori="{{ $item->kategori_id }}"
                             data-tipe="{{ $item->konsinyasi ? '1' : '0' }}"
                             data-nama="{{ strtolower($item->nama_produk) }}"
                             onclick="addToCart({{ $item->id }}, '{{ addslashes($item->nama_produk) }}', {{ $item->harga_jual }}, {{ $item->stok }})">
                            <!-- Foto -->
                            <div style="width: 100%; height: 90px; border-radius: 8px; background-color: #f1f5f9; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                                <img src="{{ $item->foto_url }}" style="width: 100%; height: 100%; object-fit: contain; padding: 6px;" alt="Foto {{ $item->nama_produk }}">
                                <div class="absolute inset-0 bg-emerald-600/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            </div>

                            <!-- Info Produk -->
                            <div class="flex flex-col flex-grow justify-between">
                                <div>
                                    <h3 style="font-size: 12px; font-weight: 600; color: #1e293b; line-height: 1.3; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $item->nama_produk }}</h3>
                                    <p style="color: #059669; font-weight: 700; font-size: 13px;">Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</p>
                                </div>
                                <div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 11px; color: #64748b;">Stok: {{ $item->stok }}</span>
                                    @if($item->stok <= 5)
                                        <span style="font-size: 9px; background-color: #ffedd5; color: #ea580c; padding: 2px 6px; border-radius: 10px; font-weight: 700;">Terbatas</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full flex flex-col items-center justify-center text-slate-400 py-10">
                            <i class="fas fa-box-open text-4xl mb-3"></i>
                            <p style="font-size: 13px;">Tidak ada produk ditemukan.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Bagian Keranjang (KANAN) -->
            <div class="pos-right" style="background-color: white; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; position: sticky; top: 16px; align-self: flex-start; border: 1px solid #e2e8f0; border-radius: 16px; z-index: 20; max-height: calc(100vh - 120px);">

                <div style="padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; border-radius: 16px 16px 0 0; flex-shrink: 0;">
                    <h2 style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0;"><i class="fas fa-shopping-cart" style="color: #059669; margin-right: 6px;"></i>Keranjang</h2>
                    <span id="cartCount" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background-color: #d1fae5; color: #047857;">0 Item</span>
                </div>

                <!-- Error Messages dari Laravel Validation -->
                @if ($errors->any())
                    <div style="padding: 8px 12px 0 12px; flex-shrink: 0;">
                        <div style="background-color: #fef2f2; color: #dc2626; padding: 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">
                            @foreach ($errors->all() as $error)
                                <div style="margin-bottom: 2px;">- {{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (session()->has('success'))
                    <div style="padding: 8px 12px 0 12px; flex-shrink: 0;">
                        <div style="background-color: #f0fdf4; color: #15803d; padding: 8px; border-radius: 8px; font-size: 11px; font-weight: 600; text-align: center;">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif
                <div id="jsErrorAlert" style="padding: 8px 12px 0 12px; display: none; flex-shrink: 0;">
                    <div id="jsErrorText" style="background-color: #fef2f2; color: #dc2626; padding: 8px; border-radius: 8px; font-size: 11px; font-weight: 600;"></div>
                </div>

                <!-- Daftar Item -->
                <div id="cartItemsContainer" style="padding: 12px; display: flex; flex-direction: column; gap: 8px; flex: 1 1 auto; overflow-y: auto;">
                    <!-- Diisi via JS -->
                    <div style="display: flex; flex-direction: column; items-center; justify-content: center; color: #94a3b8; height: 100px; text-align: center;">
                        <div style="width: 40px; height: 40px; background-color: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto;">
                            <i class="fas fa-shopping-basket" style="font-size: 16px; color: #cbd5e1;"></i>
                        </div>
                        <p style="font-size: 11px; margin: 0;">Keranjang masih kosong</p>
                    </div>
                </div>

                <!-- Hidden Input untuk dikirim ke Controller -->
                <div id="hiddenCartInputs"></div>

                <!-- Pembayaran & Total -->
                <div style="padding: 12px 16px; background-color: white; border-top: 1px solid #e2e8f0; border-radius: 0 0 16px 16px;">

                    <!-- Pengaturan Form -->
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                            <div style="width: 50%;">
                                <label style="display: block; font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Tipe Pelanggan</label>
                                <select name="tipe_pelanggan" id="tipe_pelanggan" onchange="updateIdentitasState()" style="width: 100%; height: 32px; padding: 0 8px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; color: #334155; outline: none; cursor: pointer;">
                                    <option value="umum">Umum</option>
                                    <option value="anggota">Anggota</option>
                                    <option value="karyawan">Karyawan</option>
                                </select>
                            </div>
                            <div style="width: 50%;">
                                <label style="display: block; font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Identitas</label>

                                <select name="anggota_id" id="anggota_id" style="display: none; width: 100%; height: 32px; padding: 0 8px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; color: #334155; outline: none; cursor: pointer;">
                                    <option value="">Pilih Anggota...</option>
                                    @foreach($anggota as $ang)
                                        <option value="{{ $ang->id }}">{{ $ang->karyawan->nama ?? 'Tanpa Nama' }}</option>
                                    @endforeach
                                </select>

                                <select name="karyawan_id" id="karyawan_id" style="display: none; width: 100%; height: 32px; padding: 0 8px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; color: #334155; outline: none; cursor: pointer;">
                                    <option value="">Pilih Karyawan...</option>
                                    @foreach($karyawanNonAnggota as $kar)
                                        <option value="{{ $kar->id }}">{{ $kar->nama }}</option>
                                    @endforeach
                                </select>

                                <div id="identitas_umum" style="width: 100%; height: 32px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; padding: 0 8px; font-size: 12px; color: #94a3b8;">
                                    Umum
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Pembayaran -->
                        <div style="display: flex; gap: 8px;">
                            <div style="width: 50%;">
                                <label style="display: block; font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Metode Bayar</label>
                                <select name="metode_pembayaran" id="metode_pembayaran" onchange="updateDompetState()" style="width: 100%; height: 32px; padding: 0 8px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; color: #334155; outline: none; cursor: pointer;">
                                    <option value="tunai">Tunai</option>
                                    <option value="transfer_bank">Transfer</option>
                                    <option value="qris">QRIS</option>
                                    <option value="potong_gaji" id="opt_potong_gaji" style="display: none;">Kasbon (Potong Gaji)</option>
                                </select>
                            </div>
                            <div style="width: 50%;">
                                <label style="display: block; font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Penyimpanan</label>

                                <select name="dompet_id" id="dompet_id" style="width: 100%; height: 32px; padding: 0 8px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; color: #334155; outline: none; cursor: pointer;">
                                    <option value="">Pilih Dompet...</option>
                                    @foreach($dompets as $dompet)
                                        <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }}</option>
                                    @endforeach
                                </select>

                                <div id="dompet_payroll" style="display: none; width: 100%; height: 32px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; align-items: center; padding: 0 8px; font-size: 12px; color: #94a3b8;">
                                    Payroll
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rincian Biaya -->
                    <div style="margin-bottom: 12px;">
                        <div style="border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-bottom: 8px;">

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 500;">Subtotal</span>
                                <span style="font-size: 13px; font-weight: 700; color: #334155;" id="lbl_subtotal">Rp 0</span>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 500;">Diskon</span>
                                <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; height: 26px; width: 100px; overflow: hidden;">
                                    <span style="padding: 0 6px; font-size: 11px; font-weight: 700; color: #94a3b8; background-color: #f1f5f9; border-right: 1px solid #e2e8f0; height: 100%; display: flex; align-items: center;">Rp</span>
                                    <input type="number" name="diskon" id="diskon" value="0" oninput="calculateTotals()" style="width: 100%; height: 100%; border: none; background: transparent; text-align: right; padding-right: 6px; font-size: 12px; font-weight: 600; outline: none; box-shadow: none;">
                                </div>
                            </div>

                            <div id="uang_diterima_container" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 500;">Uang Diterima</span>
                                <div style="display: flex; align-items: center; border: 1px solid #34d399; border-radius: 6px; background-color: #ecfdf5; height: 28px; width: 120px; overflow: hidden;">
                                    <span style="padding: 0 6px; font-size: 11px; font-weight: 700; color: #10b981; background-color: #d1fae5; border-right: 1px solid #a7f3d0; height: 100%; display: flex; align-items: center;">Rp</span>
                                    <input type="number" id="uang_diterima" oninput="calculateTotals()" style="width: 100%; height: 100%; border: none; background: transparent; text-align: right; padding-right: 6px; font-size: 13px; font-weight: 700; color: #059669; outline: none; box-shadow: none;" placeholder="0">
                                </div>
                            </div>

                            <div id="kembalian_container" style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 500;">Kembalian</span>
                                <span style="font-size: 13px; font-weight: 700; color: #334155;" id="lbl_kembalian">Rp 0</span>
                            </div>
                        </div>

                        <div style="background-color: #1e293b; color: #ffffff; padding: 10px 14px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                            <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #cbd5e1;">Total Bayar</span>
                            <span style="font-size: 18px; font-weight: 900; color: #34d399; letter-spacing: -0.5px;" id="lbl_grandtotal">Rp 0</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 8px;">
                        <button type="button" onclick="clearCart()" style="width: 30%; height: 40px; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: 8px; background-color: #fef2f2; color: #ef4444; font-weight: bold; font-size: 13px; border: 1px solid #fee2e2; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.backgroundColor='#ef4444'; this.style.color='white';" onmouseout="this.style.backgroundColor='#fef2f2'; this.style.color='#ef4444';">
                            <i class="fas fa-trash-alt" style="font-size: 13px;"></i>
                            <span>BATAL</span>
                        </button>
                        <button type="button" onclick="submitCheckout()" style="width: 70%; height: 40px; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: 8px; background-color: #10b981; color: white; font-weight: 700; font-size: 13px; border: none; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3); transition: all 0.2s; cursor: pointer;" onmouseover="this.style.backgroundColor='#059669';" onmouseout="this.style.backgroundColor='#10b981';">
                            <i class="fas fa-check-circle" style="font-size: 14px;"></i>
                            <span>PROSES BAYAR</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

    /* Elegant thin scrollbar for the cart items */
    #cartItemsContainer::-webkit-scrollbar {
        width: 6px;
    }
    #cartItemsContainer::-webkit-scrollbar-track {
        background: transparent;
    }
    #cartItemsContainer::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 10px;
    }
    #cartItemsContainer::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8;
    }

    .pos-container { min-height: calc(100vh - 100px); }
    @media (max-width: 1024px) {
        .pos-container { min-height: auto; }
    }
</style>

<script>
    let cart = [];
    let subtotal = 0;
    let grandTotal = 0;

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    let currentKategori = 'all';
    let currentTipe = 'all';
    let currentSearch = '';

    // SEARCH & FILTER
    document.getElementById('searchInput').addEventListener('input', function(e) {
        currentSearch = e.target.value.toLowerCase();
        applyFilters();
    });

    function filterTipeDropdown(tipe) {
        currentTipe = tipe;
        applyFilters();
    }

    function filterKategoriDropdown(id) {
        currentKategori = id;
        applyFilters();
    }

    function applyFilters() {
        let cards = document.querySelectorAll('.produk-card');
        cards.forEach(card => {
            let matchSearch = card.getAttribute('data-nama').includes(currentSearch);
            let matchKategori = (currentKategori === 'all' || card.getAttribute('data-kategori') == currentKategori);
            let matchTipe = (currentTipe === 'all' || card.getAttribute('data-tipe') == currentTipe);

            if(matchSearch && matchKategori && matchTipe) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // FUNGSI CART (KERANJANG)IC
    function addToCart(id, nama, harga, stok) {
        let existing = cart.find(item => item.id == id);
        if(existing) {
            if(existing.qty < stok) existing.qty++;
        } else {
            if(stok > 0) {
                cart.push({id: id, nama: nama, harga: harga, qty: 1, max: stok});
            }
        }
        renderCart();
    }

    function increaseQty(index) {
        if(cart[index].qty < cart[index].max) cart[index].qty++;
        renderCart();
    }

    function decreaseQty(index) {
        if(cart[index].qty > 1) {
            cart[index].qty--;
            renderCart();
        } else {
            removeItem(index);
        }
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function clearCart() {
        cart = [];
        renderCart();
    }

    function renderCart() {
        let container = document.getElementById('cartItemsContainer');
        document.getElementById('cartCount').innerText = cart.length + ' Item';

        // Reset subtotal at the start of renderCart so it becomes 0 when cart is empty
        subtotal = 0;

        if(cart.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center text-slate-400" style="height: 100%;">
                    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-shopping-basket text-2xl text-slate-300"></i>
                    </div>
                    <p class="text-xs">Keranjang masih kosong</p>
                </div>
            `;
        } else {
            let html = '';
            cart.forEach((item, index) => {
                let itemTotal = item.harga * item.qty;
                subtotal += itemTotal;
                html += `
                <div style="display: flex; align-items: center; gap: 8px; padding: 10px; background-color: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; position: relative;" class="group">
                    <button type="button" onclick="removeItem(${index})" style="position: absolute; right: -6px; top: -6px; background-color: #fee2e2; color: #ef4444; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: none; cursor: pointer;" class="group-hover:opacity-100">
                        <i class="fas fa-times" style="font-size: 10px;"></i>
                    </button>
                    <div style="flex-grow: 1; overflow: hidden;">
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.nama}</div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 12px; color: #059669; font-weight: bold;">Rp ${formatRupiah(item.harga)}</span>
                            <span style="font-size: 13px; font-weight: bold; color: #1e293b;">Rp ${formatRupiah(itemTotal)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                            <span style="font-size: 11px; color: #94a3b8;">Kuantitas:</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" onclick="decreaseQty(${index})" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: white; border: 1px solid #e2e8f0; color: #475569; cursor: pointer;">
                                    <span style="font-size: 16px; font-weight: bold; line-height: 1; margin-top: -2px;">-</span>
                                </button>
                                <span style="font-size: 12px; font-weight: bold; width: 20px; text-align: center;">${item.qty}</span>
                                <button type="button" onclick="increaseQty(${index})" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: #d1fae5; border: 1px solid #a7f3d0; color: #047857; cursor: pointer;">
                                    <span style="font-size: 16px; font-weight: bold; line-height: 1; margin-top: -1px;">+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                `;
            });
            container.innerHTML = html;
        }

        calculateTotals();
        renderHiddenInputs();
    }

    function calculateTotals() {
        document.getElementById('lbl_subtotal').innerText = 'Rp ' + formatRupiah(subtotal);
        let diskon = parseInt(document.getElementById('diskon').value) || 0;
        grandTotal = subtotal - diskon;
        if(grandTotal < 0) grandTotal = 0;
        document.getElementById('lbl_grandtotal').innerText = 'Rp ' + formatRupiah(grandTotal);

        let metode = document.getElementById('metode_pembayaran').value;
        if(metode === 'tunai') {
            document.getElementById('uang_diterima_container').style.display = 'flex';
            document.getElementById('kembalian_container').style.display = 'flex';
            let diterima = parseInt(document.getElementById('uang_diterima').value) || 0;
            let kembalian = diterima - grandTotal;
            let lblKembalian = document.getElementById('lbl_kembalian');
            lblKembalian.innerText = 'Rp ' + formatRupiah(kembalian);
            lblKembalian.style.color = kembalian < 0 ? '#ef4444' : '#334155';
        } else {
            document.getElementById('uang_diterima_container').style.display = 'none';
            document.getElementById('kembalian_container').style.display = 'none';
        }
    }

    function renderHiddenInputs() {
        let container = document.getElementById('hiddenCartInputs');
        let html = '';
        cart.forEach((item, index) => {
            html += `<input type="hidden" name="items[${index}][produk_id]" value="${item.id}">`;
            html += `<input type="hidden" name="items[${index}][jumlah]" value="${item.qty}">`;
        });
        container.innerHTML = html;
    }

    // FORM STATE
    function updateIdentitasState() {
        let tipe = document.getElementById('tipe_pelanggan').value;
        let anggotaEl = document.getElementById('anggota_id');
        let karyawanEl = document.getElementById('karyawan_id');

        anggotaEl.style.display = 'none';
        karyawanEl.style.display = 'none';
        document.getElementById('identitas_umum').style.display = 'none';

        let selMetode = document.getElementById('metode_pembayaran');
        let allowedForAnggota = ['tunai', 'potong_gaji'];
        [...selMetode.options].forEach(option => {
            let isAllowedForTipe = tipe === 'anggota'
                ? allowedForAnggota.includes(option.value)
                : option.value !== 'potong_gaji';
            option.hidden = !isAllowedForTipe;
            option.disabled = !isAllowedForTipe;
            option.style.display = isAllowedForTipe ? '' : 'none';
        });

        if(tipe === 'anggota') {
            anggotaEl.style.display = 'block';
            karyawanEl.value = "";
            if(!allowedForAnggota.includes(selMetode.value)) selMetode.value = 'tunai';
        } else if(tipe === 'karyawan') {
            karyawanEl.style.display = 'block';
            anggotaEl.value = "";
            if(selMetode.value === 'potong_gaji') selMetode.value = 'tunai';
        } else {
            document.getElementById('identitas_umum').style.display = 'flex';
            anggotaEl.value = "";
            karyawanEl.value = "";
            if(selMetode.value === 'potong_gaji') selMetode.value = 'tunai';
        }
        updateDompetState();
    }

    function updateDompetState() {
        let tipe = document.getElementById('tipe_pelanggan').value;
        let selMetode = document.getElementById('metode_pembayaran');

        if(selMetode.value === 'potong_gaji') {
            document.getElementById('dompet_id').style.display = 'none';
            document.getElementById('dompet_id').value = "";
            document.getElementById('dompet_payroll').style.display = 'flex';
        } else {
            document.getElementById('dompet_id').style.display = 'block';
            document.getElementById('dompet_payroll').style.display = 'none';
        }
        calculateTotals();
    }

    function showError(msg) {
        document.getElementById('jsErrorAlert').style.display = 'block';
        document.getElementById('jsErrorText').innerText = msg;
        setTimeout(() => { document.getElementById('jsErrorAlert').style.display = 'none'; }, 5000);
    }

    function submitCheckout() {
        if(cart.length === 0) {
            showError('Keranjang belanja kosong!');
            return;
        }

        let tipe = document.getElementById('tipe_pelanggan').value;
        if(tipe === 'anggota' && document.getElementById('anggota_id').value === '') {
            showError('Silakan pilih Anggota!');
            return;
        }
        if(tipe === 'karyawan' && document.getElementById('karyawan_id').value === '') {
            showError('Silakan pilih Karyawan!');
            return;
        }

        let metode = document.getElementById('metode_pembayaran').value;
        if(tipe === 'anggota' && !['tunai', 'potong_gaji'].includes(metode)) {
            showError('Anggota hanya dapat memilih Tunai atau Potong Gaji.');
            return;
        }
        if(metode !== 'potong_gaji' && document.getElementById('dompet_id').value === '') {
            showError('Silakan pilih Dompet Penyimpanan!');
            return;
        }

        if(metode === 'tunai') {
            let diterima = parseInt(document.getElementById('uang_diterima').value) || 0;
            if(diterima < grandTotal) {
                showError('Uang diterima kurang dari total belanja!');
                return;
            }
        }

        document.getElementById('posForm').submit();
    }

    // Init
    updateIdentitasState();
</script>
@endsection
