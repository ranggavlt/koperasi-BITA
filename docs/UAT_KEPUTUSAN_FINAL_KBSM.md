# UAT Keputusan Final Koperasi KBSM

Dokumen ini menjadi checklist presentasi client. Gunakan data dummy hasil `php artisan db:seed` dan tandai setiap skenario sebagai **Diterima**, **Revisi kecil**, atau **Ditunda**.

## Akun demo

| Peran | Email | Password | Hasil yang diharapkan |
|---|---|---|---|
| Finance | `keuangan@kbsm.test` | `Kbsm12345!` | Seluruh transaksi keuangan dan master yang diizinkan |
| Kasir | `kasir@kbsm.test` | `Kbsm12345!` | POS, pembayaran konsinyasi, dan laporan konsinyasi |

Public registration harus menghasilkan 404. User nonaktif harus gagal login dan dikeluarkan jika session lama masih aktif. Role Karyawan tidak boleh membuka transaksi keuangan melalui URL langsung.

## Keputusan implementasi PG-2

Tiga hal yang belum dijawab pada prompt diterapkan secara konservatif:

1. Manasuka rutin bersifat **full-only**. Jika sisa limit tidak cukup, setoran tidak dialokasikan sebagian.
2. Permintaan/perubahan hanya dicatat Finance; tidak ada self-service Karyawan.
3. Konfigurasi otomatis dijeda mulai periode berikutnya ketika Anggota/Karyawan nonaktif.

Prioritas payroll final: Cicilan Pinjaman, Simpanan Wajib, Manasuka rutin, lalu Waserba kredit.

## Skenario UAT

### 1. Akses dan perusahaan

- Pastikan hanya role `admin`, `kasir`, dan `karyawan` tersedia.
- Pastikan perusahaan resmi adalah BEE — Bita Enarcon Engineering, BBS — Bita Bina Semesta, dan BKM — Bamko Karsa Mandiri.
- Nonaktifkan satu user, lalu uji login baru dan session yang masih terbuka.

### 2. Simpanan dan payroll

- Daftarkan Anggota baru. Sistem harus membuat satu tagihan Wajib Rp10.000 untuk siklus tersebut dengan default potong gaji.
- Bayar Wajib melalui Kas dan ulangi request yang sama. Saldo, Mutasi, dan Jurnal hanya boleh bertambah sekali.
- Uji Transfer Bank dengan Dompet Bank; Dompet yang tidak sesuai metode harus ditolak.
- Nonaktifkan lalu daftar ulang Anggota. Siklus baru harus mendapat satu Wajib Rp10.000 baru; jadwal berkala tidak boleh dibuat.
- Setor dan tarik Simpanan Manasuka melalui Kas/Bank. Kode transaksi harus berawalan `SMN-YYYYMM-`.
- Aktifkan Manasuka rutin, ubah nominal, jeda, aktifkan kembali, dan hentikan. Semua perubahan berlaku periode berikutnya.
- Uji limit umum Rp1.500.000, override persisten, reset otomatis ke limit umum, dan toggle kredit Waserba.
- Pastikan mematikan kredit Waserba tidak mematikan Cicilan Pinjaman atau Simpanan Wajib.

### 3. Sewa Hardware dan Mobil B2B

- Buat Sewa Hardware vendor dengan lebih dari satu unit/jenis. Margin tiap baris harus 15% dan dibulatkan half-up; kode berawalan `SWH-YYYYMM-`.
- Buat Sewa Mobil memakai snapshot vendor/kendaraan dan total periode, lalu ajukan dan setujui menggunakan Pengurus aktif.
- Pastikan tidak ada pilihan Master Printer/Mobil atau tarif harian pada form transaksi baru.
- Bayar vendor dari Dompet Kas yang ditandai Kas Operasional. Bank atau Kas non-operasional harus ditolak.
- Gabungkan beberapa sewa eligible milik satu perusahaan menjadi satu invoice. Transaksi perusahaan lain atau yang sudah masuk invoice harus ditolak.
- Bayar invoice perusahaan secara partial dua kali. Periksa total, terbayar, sisa, histori, Mutasi, dan Jurnal setiap pembayaran.
- Pastikan pembayaran vendor dan pembayaran perusahaan tampil sebagai dua kejadian keuangan terpisah.

### 4. Pinjaman dan POS

- Jalankan lifecycle Pinjaman: draft, diajukan, disetujui, pencairan/aktif, Cicilan, dan lunas.
- Pastikan maksimal Rp5.000.000, bunga 0%, biaya admin Rp50.000, tenor 1–12 bulan, dan satu proses terbuka per Anggota.
- Uji ditolak/dibatalkan dan peminjaman kembali setelah lunas.
- Uji POS tunai dan potong gaji. POS tunai tidak boleh membuat ledger payroll.
- Uji stok, konsinyasi, pembayaran reseller, laporan konsinyasi, reversal, dan pengulangan checkout/idempotency.

### 5. Klaim Dana Sosial

- Tambah sumber dana yang disetujui, lalu ajukan klaim meninggal, melahirkan, khitan, atau proposal sosial.
- Uji alur draft, diajukan, disetujui/ditolak, dan pembayaran.
- Klaim melebihi saldo tersedia harus ditolak.
- Pembayaran harus mengurangi sumber, mengurangi Dompet, serta membuat Mutasi Dana Sosial, Mutasi Kas, dan Jurnal balance tepat satu kali.

### 6. SHU dan fitur ditunda

- Menu/route SHU tetap tersembunyi selama `SHU_ENABLED=false`.
- Tidak boleh ada hard delete transaksi/snapshot SHU atau pencairan langsung dari kode legacy.
- Jasa Print, Master Mobil, Master Printer, public registration, mixed payment, dan self-service Karyawan tidak boleh aktif.
- Aktivasi SHU ditunda sampai client menyetujui persentase Pembina/Pengawas/Pengurus/Anggota/Dana Sosial, formula Anggota, tanggal tutup, sumber snapshot, accounting, dan reversal.

## Release checklist

- Seluruh targeted test dan full test lulus.
- Semua command `koperasi:preflight-*` bersih.
- Route list, view cache, dan build Vite berhasil.
- Tidak ada conflict marker, whitespace error, jurnal tidak balance, orphan, atau duplicate business/idempotency key.
- GET laporan terbukti read-only.
- Feature flag SHU, Jasa Print, Master Printer, dan fitur ditunda tetap mati.
