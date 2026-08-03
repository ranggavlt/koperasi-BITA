# UAT Checklist KBSM

Status keseluruhan: **NOT READY** sampai UAT transaksi client untuk workflow baru dan checkpoint casing Git Pinjaman diselesaikan. Keputusan SHU/Dana Sosial sudah diimplementasikan; feature flag tetap false sampai UAT diterima.

| ID | Skenario | Expected result | Bukti otomatis | Browser actual | Status |
|---|---|---|---|---|---|
| UAT-01 | Login Finance/Kasir/Karyawan/nonaktif/Guest | Redirect dan akses sesuai role; nonaktif ditolak | `AccessMatrixTest` | Finance ke Dashboard; Kasir ke Waserba; Karyawan baru ke ganti password; nonaktif ditolak; Guest ke login; `/register` 404 | Pass |
| UAT-02 | Pendaftaran Anggota | Wajib Rp10.000 tepat satu kali per siklus | SP-7 tests/preflight | Belum direkam final | Automated pass |
| UAT-03 | Manasuka manual | Setor/tarik/koreksi, saldo tidak negatif, counter-entry | SP-3 tests/preflight | Belum direkam final | Automated pass |
| UAT-04 | Manasuka Rutin | Next-period, full-only, confirm/release idempotent | `ManasukaRutinPg2Test` | Belum direkam final | Automated pass |
| UAT-05 | Prioritas payroll | Cicilan → Wajib → Manasuka → Waserba | PG/Pinjaman tests | Belum direkam final | Automated pass |
| UAT-06 | Override limit | Berlaku bulan depan dan dapat kembali ke limit umum | PG-1 tests/preflight | Belum direkam final | Automated pass |
| UAT-07 | Lifecycle Pinjaman | Draft sampai cair/cicil/lunas tanpa posting dini | Pinjaman lifecycle/payroll tests | Belum direkam final | Automated pass |
| UAT-08 | Waserba/konsinyasi | Tunai/payroll/eligibility/reversal/report benar | POS 2D/2E dan Konsinyasi tests | Belum direkam final | Automated pass |
| UAT-09 | Sewa Mobil B2B | Vendor dibayar dahulu, invoice partial, snapshot tetap | Sewa Mobil/B2B tests | Form dapat menerima fokus/input dan tombol Buat Draft aktif; transaksi client belum dieksekusi | Automated + browser smoke pass |
| UAT-10 | Sewa Hardware B2B | Detail dinamis, margin 15%, invoice partial | Sewa Hardware/B2B tests | Form dapat menerima fokus/input, Tambah Hardware menambah baris, tombol Buat Draft aktif, desktop/mobile tidak overflow | Automated + browser smoke pass |
| UAT-11 | Rekonsiliasi | Dompet/Mutasi/Jurnal/vendor/invoice/payroll konsisten | financial reconciliation preflight | Belum direkam final | Automated pass |
| UAT-12 | Periode Akuntansi | Laba dari jurnal posted, closing balance, periode terkunci, koreksi counter-entry | `AccountingPeriodWorkflowTest`, accounting preflight | Belum direkam operator | Automated pass; manual pending |
| UAT-13 | Karyawan keluar/daftar ulang | Settlement, offset/refund dan siklus baru benar | Keanggotaan tests/preflight | Belum direkam final | Automated pass |
| UAT-14 | Reversal | Bukti asli tetap ada dan counter-entry balance | reversal tests dan accounting preflight | Belum direkam final | Automated pass |
| UAT-15 | SHU closed-period | Tidak ada input laba manual; config snapshot; approval, posting, dan reversal exact | `ShuKoperasiTest`, `ShuConfigurationTest`, preflight SHU | Belum direkam operator | Automated pass; manual pending |
| UAT-16 | Donasi maker-checker | Draft tidak mengubah Dompet; checker berbeda membuat Debit Dompet/Kredit 210 | `DanaSosialFinalTest`, preflight Dana Sosial | Belum direkam operator dengan dua Admin | Automated pass; manual pending |
| UAT-17 | Batas dan Klaim Dana Sosial | Batas berversi, self-approval ditolak, saldo tidak negatif, payout/reversal balance | `DanaSosialFinalTest`, preflight Dana Sosial | Belum direkam operator | Automated pass; manual pending |
| UAT-18 | Rekap B2B per perusahaan | Total tagihan, terbayar, dan sisa BEE/BBS/BKM sesuai invoice partial | `B2BFinalTest`, preflight B2B | Belum direkam operator | Automated pass; manual pending |

## Checklist UI desktop dan mobile

- [x] Sidebar tidak menutupi konten desktop pada halaman smoke.
- [ ] Backdrop, Escape, dan body scroll drawer perlu konfirmasi manual; drawer dan accordion sudah lulus browser smoke 390px.
- [x] Navbar search menampilkan modul yang dapat diakses Finance pada browser smoke.
- [x] Tidak ada 404/403/500 yang tidak diharapkan pada 10 halaman smoke utama.
- [ ] Seluruh validation error dan tombol transaksi perlu UAT operator/client; kontrol Sewa Mobil/Hardware sudah lulus smoke.
- [ ] Tabel, status, pagination, dan empty state terbaca pada desktop/mobile.
- [x] Detail Hardware dapat diinput, ditambah, dan tidak terpotong pada desktop serta mobile 390px.
- [ ] Submit transaksi Sewa Mobil, Pinjaman, Simpanan, dan Invoice menunggu UAT operator/client; tombol dan kontrol utama sudah aktif pada smoke.

## Bukti browser smoke 3 Agustus 2026

- Runtime: Microsoft Edge headless, desktop 1440x900 dan mobile 390x844.
- Halaman Finance: Dashboard, Manajemen User, Cicilan Pinjaman, Transaksi Simpanan, Sewa Mobil (list/create), Sewa Hardware (list/create), Invoice Penagihan, dan Mutasi Kas.
- Hasil: seluruh URL tepat, tanpa server error, console/network error, literal Blade, atau horizontal overflow.
- Role gate: Guest, Kasir, Karyawan first-login, akun nonaktif, dan Finance seluruhnya sesuai expected route.
- Aset Font Awesome telah dipindahkan dari kit eksternal yang mengembalikan 403 ke bundle Vite lokal.

## Keputusan SHU yang sudah diterima

- Persentase alokasi diisi oleh Admin pada menu Pengaturan SHU.
- Total pos pembagian wajib tepat 100%; Jasa Modal + Jasa Usaha juga wajib tepat 100%.
- Setiap simpan membuat versi histori baru dengan tanggal berlaku, dasar keputusan, Admin penyimpan, dan waktu approval.
- Periode SHU baru mengambil snapshot versi yang berlaku; perubahan berikutnya tidak mengubah periode lama.
- Laba bersumber otomatis dari jurnal posted pada periode akuntansi closed; nominal tidak dapat diketik Admin.
- Posting alokasi memakai COA 304 sebagai sumber dan membuat sumber Dana Sosial COA 210 tepat satu kali.
- Dana Sosial manual hanya Donasi Resmi melalui Dompet dan wajib maker-checker dua Admin berbeda.
- Batas Kematian, Kelahiran, Khitan, dan Proposal Sosial dibuat berversi dengan tanggal berlaku.

## Pencatatan issue

Setiap issue browser harus mencatat role, URL, viewport, langkah reproduksi, expected, actual, screenshot, severity, serta regression test setelah diperbaiki.
