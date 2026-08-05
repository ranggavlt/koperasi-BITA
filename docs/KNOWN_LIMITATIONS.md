# Known Limitations KBSM

Dokumen ini hanya mencatat limitation yang nyata. Belum ada limitation yang dianggap disetujui client tanpa konfirmasi tertulis.

## Blocker aktif

### Feature gate SHU

- `FEATURE_SHU_ENABLED=false` adalah default wajib.
- Persentase sudah diputuskan sebagai pengaturan Admin: setiap perubahan membuat versi immutable, total pembagian wajib 100%, dan periode baru menyimpan snapshot versi yang berlaku.
- Source laba jurnal posted, tutup periode, posting, reversal, dan alokasi sudah diimplementasikan; flag tetap false sampai migration, preflight, dan UAT final lulus.
- Direct URL SHU mengembalikan 404 saat disabled.
- Halaman `Pengaturan SHU` tetap tersedia khusus Admin agar konfigurasi dapat disiapkan tanpa membuka operasional SHU.

### Feature gate Dana Sosial

- `FEATURE_DANA_SOSIAL_ENABLED=false` adalah default wajib.
- Dana Sosial juga memerlukan SHU aktif; mengaktifkan flag Dana tanpa SHU tetap menghasilkan 404.
- Sumber alternatif final hanya Donasi Resmi melalui Dompet; konfigurasi kompatibilitas `FEATURE_DANA_SOSIAL_ALTERNATIVE_SOURCES_ENABLED=true`.
- Batas klaim berversi dan maker-checker dua Admin sudah tersedia; flag utama tetap false sampai final gate.

### Termin invoice B2B

- Sistem tidak menebak 14/30 hari.
- Finance wajib memilih tanggal jatuh tempo secara eksplisit ketika finalisasi invoice.
- Denda keterlambatan belum diimplementasikan karena belum ada keputusan client.
- Bunga dan masa tenggang otomatis juga tidak diimplementasikan sesuai keputusan final.

### Casing Git Pinjaman

- Filesystem Windows menampilkan nama class yang benar, tetapi index Git lama masih menyimpan variasi lowercase.
- Operator harus melakukan casing-only rename pada Git sebelum checkpoint agar deployment Linux portabel.

## Bukan limitation

- Selisih saldo Dompet terhadap total Mutasi bukan bug bila opening balance diperhitungkan. Opening balance sekarang disimpan pada `dompet_koperasi.saldo_awal`.
- Master Mobil/Printer tidak diperlukan oleh Sewa Mobil/Hardware karena rental memakai snapshot vendor.
