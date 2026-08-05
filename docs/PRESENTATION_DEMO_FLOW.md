# Presentation Demo Flow KBSM

Target durasi demo inti: 25–35 menit. Gunakan database demo deterministik dari `DatabaseSeeder`.

## 1. Login dan akses role

1. Login Finance menggunakan akun demo.
2. Tunjukkan Dashboard, Manajemen User, dan bahwa POS tidak tersedia bagi Finance.
3. Login Kasir; tunjukkan Waserba, Pembayaran Konsinyasi, dan Laporan Konsinyasi.
4. Tunjukkan route Finance ditolak untuk Kasir.

## 2. Keanggotaan dan Simpanan

1. Buat/lihat Karyawan dan Anggota aktif.
2. Tunjukkan Simpanan Wajib Rp10.000 satu kali per siklus.
3. Lakukan setoran dan penarikan Manasuka melalui Kas/Bank.
4. Tunjukkan koreksi Manasuka menghasilkan counter-entry dan jurnal asli tetap ada.

## 3. Payroll

1. Buka periode payroll dan limit Rp1.500.000.
2. Tunjukkan override lalu “Kembalikan ke Limit Umum”.
3. Demonstrasikan prioritas Cicilan → Wajib → Manasuka Rutin → Waserba.
4. Tunjukkan release/cancel dan confirm tanpa duplicate posting.

## 4. Pinjaman

1. Buat draft, ajukan, setujui, lalu cairkan sebagai aksi berbeda.
2. Sebelum cair tunjukkan belum ada jadwal/Mutasi/jurnal pencairan.
3. Setelah cair tunjukkan jadwal, Mutasi, jurnal, dan cicilan payroll/tunai.

## 5. Waserba dan Konsinyasi

1. Kasir melakukan penjualan tunai.
2. Lakukan penjualan payroll untuk Anggota eligible.
3. Tunjukkan penolakan kredit bagi Anggota yang eligibility-nya dimatikan.
4. Tunjukkan pembayaran reseller dan report Konsinyasi read-only untuk Finance.

## 6. Rental B2B

1. Buat Sewa Mobil vendor-based, approval Pengurus, lalu bayar vendor dari Kas Operasional.
2. Buat Sewa Hardware dengan beberapa detail dan margin 15% half-up.
3. Gabungkan transaksi satu perusahaan ke invoice; pilih jatuh tempo eksplisit.
4. Bayar invoice secara partial, lalu lunasi; tunjukkan overpayment ditolak.

## 7. Rekonsiliasi

1. Buka Mutasi Kas & Bank dan Buku Besar.
2. Tunjukkan COA 209 dan 210 terpisah.
3. Jalankan preflight financial reconciliation dan tunjukkan seluruh count nol.

## 8. Fitur deferred

Tunjukkan bahwa SHU dan Dana Sosial tidak ada di sidebar/search dan direct URL 404. Jelaskan bahwa keduanya menunggu keputusan bisnis, bukan error aplikasi.
