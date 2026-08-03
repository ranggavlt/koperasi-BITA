# Final Feature Matrix KBSM

Status dokumen ini mengikuti repository dan database demo lokal. Keputusan SHU, Donasi, batas klaim, maker-checker, dan B2B sudah diimplementasikan. Status produk tetap **NOT READY** sampai migration/seed lokal, seluruh preflight, UAT browser final, dan checkpoint casing Git Pinjaman selesai.

| Domain | Keputusan final | Implementasi/bukti | Status |
|---|---|---|---|
| Role | Admin = Finance, Kasir = POS, Karyawan tidak mengelola Finance | `AccessMatrixTest`, middleware `active_user`, `password_changed`, dan `role:*` | Sesuai |
| Perusahaan | BEE, BBS, BKM | `PerusahaanSeeder` dan database demo | Sesuai |
| Simpanan Wajib | Rp10.000 satu kali per siklus | `SimpananWajibService`, SP-7 tests/preflight | Sesuai |
| Simpanan Manasuka | Manual Kas/Bank, tidak negatif, reversal, kode SMN | `SimpananManasukaService`, SP-3 tests/preflight | Sesuai |
| Manasuka Rutin | Nominal per anggota per bulan, next period, payroll atomik | `ManasukaRutinService`, `ManasukaRutinPg2Test` | Sesuai; kebijakan bulanan perlu disahkan client bila berbeda |
| Payroll | Limit umum Rp1.500.000 dan prioritas Cicilan → Wajib → Manasuka → Waserba | `PotongGajiBulananService`, PG/Pinjaman tests | Sesuai |
| Pinjaman | Finance lifecycle, 0%, admin Rp50.000, maksimal Rp5 juta, tenor 1–12 | Pinjaman lifecycle/payroll/report tests | Sesuai secara runtime; casing Git request masih perlu checkpoint operator |
| Waserba/POS | Satu checkout Kasir, tunai/payroll, eligibility, reversal | `PosCheckoutService`, Tahap 2D/2E tests | Sesuai |
| Konsinyasi | Kasir membayar reseller; Finance/Kasir dapat membaca report | Konsinyasi service/controller dan access tests | Sesuai |
| Sewa Mobil | Vendor snapshot, total periode, approval Pengurus, tanpa payroll | `SewaMobilService`, `SewaMobilTest` | Sesuai |
| Sewa Hardware | Vendor snapshot, detail dinamis, margin 15% half-up | `SewaHardwareService`, `SewaHardwareTest` | Sesuai |
| B2B | Vendor dibayar dahulu, jatuh tempo manual, pembayaran partial, rekap per BEE/BBS/BKM, tanpa denda/bunga | `B2BRentalService`, `B2BFinalTest`, preflight B2B | Sesuai |
| Accounting Core | Jurnal posted, periode open/closed, snapshot laba, jurnal penutup, date lock, koreksi counter-entry | `AccountingPeriodService`, `AccountingPeriodWorkflowTest`, accounting preflight | Sesuai; menunggu UAT final |
| Rekonsiliasi | Opening balance + Mutasi, jurnal, invoice, vendor, payroll | `koperasi:preflight-financial-reconciliation` | Sesuai pada database demo |
| SHU | Laba otomatis dari jurnal posted periode closed; persentase versioned; approval, posting, reversal, dan alokasi exact | `ShuKoperasiService`, `ShuKoperasiTest`, `shu_enabled=false`, preflight SHU | Implementasi sesuai; flag menunggu final gate |
| Dana Sosial | Alokasi SHU/Donasi Resmi, Dompet, COA 210, batas berversi, maker-checker, saldo nonnegatif, reversal | `DanaSosialService`, `DanaSosialFinalTest`, preflight Dana Sosial | Implementasi sesuai; flag menunggu final gate |
| Jasa Print/Master Printer/Master Mobil | Deferred/hidden | feature flag dan access preflight | Sesuai |

## Gate tersisa

1. Jalankan migration dan seed dummy pada database UAT, lalu seluruh preflight/integrity query.
2. Jalankan UAT browser dengan dua Admin berbeda dan dokumentasikan hasilnya.
3. Operator Git merapikan casing dua file Request Pinjaman; Codex tidak melakukan Git write.
