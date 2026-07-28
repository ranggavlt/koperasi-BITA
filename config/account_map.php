<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master akun sistem
    |--------------------------------------------------------------------------
    |
    | Key di sebelah kiri adalah identifier stabil yang dipakai oleh kode.
    | Kode akun tidak boleh ditulis langsung di controller atau service.
    | Setiap akun di bawah ini disinkronkan ke tabel `akun` oleh migration dan
    | AkunSeeder. Akun tambahan buatan pengguna tetap disimpan di tabel akun.
    |
    */
    'accounts' => [
        'kas' => [
            'kode_akun' => '101',
            'nama_akun' => 'Kas',
            'kategori' => 'aset',
            'posisi_saldo' => 'debit',
            'keterangan' => 'Uang tunai yang dikuasai koperasi.',
        ],
        'bank' => [
            'kode_akun' => '102',
            'nama_akun' => 'Bank',
            'kategori' => 'aset',
            'posisi_saldo' => 'debit',
            'keterangan' => 'Saldo rekening bank atas nama koperasi.',
        ],
        'piutang_anggota' => [
            'kode_akun' => '103',
            'nama_akun' => 'Piutang Anggota (Potong Gaji)',
            'kategori' => 'aset',
            'posisi_saldo' => 'debit',
            'keterangan' => 'Tagihan penjualan kepada anggota yang dibayar melalui potong gaji.',
        ],
        'piutang_pinjaman' => [
            'kode_akun' => '105',
            'nama_akun' => 'Piutang Pinjaman Anggota',
            'kategori' => 'aset',
            'posisi_saldo' => 'debit',
            'keterangan' => 'Pokok pinjaman anggota yang belum diterima kembali.',
        ],
        'persediaan_barang' => [
            'kode_akun' => '106',
            'nama_akun' => 'Persediaan Barang Dagang',
            'kategori' => 'aset',
            'posisi_saldo' => 'debit',
            'keterangan' => 'Nilai perolehan persediaan milik koperasi.',
        ],
        'utang_reseller_konsinyasi' => [
            'kode_akun' => '201',
            'nama_akun' => 'Utang Reseller Konsinyasi',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Hak reseller atas barang konsinyasi yang telah terjual dan belum dibayar.',
        ],
        'simpanan_sukarela' => [
            'kode_akun' => '202',
            'nama_akun' => 'Simpanan Sukarela Anggota',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Simpanan anggota yang dapat ditarik sesuai ketentuan koperasi.',
        ],
        'simpanan_khusus' => [
            'kode_akun' => '203',
            'nama_akun' => 'Simpanan Khusus Anggota',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Simpanan berjangka atau simpanan bertujuan khusus milik anggota.',
        ],
        'utang_usaha' => [
            'kode_akun' => '204',
            'nama_akun' => 'Utang Usaha',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Kewajiban kepada pemasok selain reseller konsinyasi.',
        ],
        'utang_refund_anggota' => [
            'kode_akun' => '205',
            'nama_akun' => 'Utang Refund/Kredit Anggota',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Kewajiban koperasi kepada anggota akibat refund atau koreksi yang dikompensasikan ke payroll berikutnya.',
        ],
        'pendapatan_diterima_dimuka_sewa_mobil' => [
            'kode_akun' => '206',
            'nama_akun' => 'Pendapatan Diterima Dimuka Sewa Mobil',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Penerimaan sewa mobil sebelum kegiatan selesai.',
        ],
        'pendapatan_diterima_dimuka_sewa_printer' => [
            'kode_akun' => '207',
            'nama_akun' => 'Pendapatan Diterima Dimuka Margin Sewa Printer',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Margin sewa printer yang diterima sebelum kontrak selesai.',
        ],
        'utang_vendor_sewa_printer' => [
            'kode_akun' => '208',
            'nama_akun' => 'Utang Vendor Sewa Printer',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Kewajiban kepada vendor eksternal atas biaya dasar sewa printer.',
        ],
        'simpanan_belum_terklasifikasi' => [
            'kode_akun' => '299',
            'nama_akun' => 'Simpanan Anggota Belum Terklasifikasi',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Akun penampung migrasi untuk jenis simpanan lama yang wajib ditinjau dan dipetakan.',
        ],
        'simpanan_pokok' => [
            'kode_akun' => '301',
            'nama_akun' => 'Simpanan Pokok Anggota',
            'kategori' => 'ekuitas',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Modal tetap yang disetor anggota saat menjadi anggota koperasi.',
        ],
        'simpanan_wajib' => [
            'kode_akun' => '302',
            'nama_akun' => 'Simpanan Wajib Anggota',
            'kategori' => 'ekuitas',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Modal tambahan yang disetor anggota secara berkala.',
        ],
        'dana_cadangan' => [
            'kode_akun' => '303',
            'nama_akun' => 'Dana Cadangan Koperasi',
            'kategori' => 'ekuitas',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Bagian SHU yang ditetapkan sebagai cadangan koperasi.',
        ],
        'shu_belum_dibagi' => [
            'kode_akun' => '304',
            'nama_akun' => 'SHU Belum Dibagi',
            'kategori' => 'ekuitas',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Sisa hasil usaha yang belum ditetapkan pembagiannya.',
        ],
        'pendapatan_penjualan' => [
            'kode_akun' => '401',
            'nama_akun' => 'Pendapatan Penjualan',
            'kategori' => 'pendapatan',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Pendapatan dari penjualan barang milik koperasi dan margin konsinyasi.',
        ],
        'pendapatan_jasa_pinjaman' => [
            'kode_akun' => '402',
            'nama_akun' => 'Pendapatan Jasa Pinjaman',
            'kategori' => 'pendapatan',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Pendapatan bunga atau jasa atas pinjaman anggota.',
        ],
        'pendapatan_administrasi' => [
            'kode_akun' => '403',
            'nama_akun' => 'Pendapatan Administrasi',
            'kategori' => 'pendapatan',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Pendapatan biaya administrasi koperasi.',
        ],
        'pendapatan_sewa_mobil' => [
            'kode_akun' => '404',
            'nama_akun' => 'Pendapatan Sewa Mobil',
            'kategori' => 'pendapatan',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Pendapatan koperasi dari kegiatan sewa mobil setelah kegiatan selesai.',
        ],
        'pendapatan_sewa_printer_dasar' => [
            'kode_akun' => '405',
            'nama_akun' => 'Pendapatan Sewa Printer - Komponen Dasar',
            'kategori' => 'pendapatan',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Pendapatan komponen dasar dari kontrak sewa printer.',
        ],
        'pendapatan_margin_sewa_printer' => [
            'kode_akun' => '406',
            'nama_akun' => 'Pendapatan Margin Sewa Printer 15%',
            'kategori' => 'pendapatan',
            'posisi_saldo' => 'kredit',
            'keterangan' => 'Pendapatan margin 15% dari kontrak sewa printer.',
        ],
        'harga_pokok_penjualan' => [
            'kode_akun' => '501',
            'nama_akun' => 'Harga Pokok Penjualan',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => false,
            'keterangan' => 'Nilai perolehan barang milik koperasi yang telah terjual.',
        ],
        'beban_operasional' => [
            'kode_akun' => '502',
            'nama_akun' => 'Beban Operasional',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => true,
            'keterangan' => 'Beban umum untuk menjalankan kegiatan koperasi.',
        ],
        'beban_penyisihan_piutang' => [
            'kode_akun' => '503',
            'nama_akun' => 'Beban Penyisihan Piutang',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => false,
            'keterangan' => 'Beban penyisihan atas risiko piutang yang tidak tertagih.',
        ],
        'beban_sosial' => [
            'kode_akun' => '504',
            'nama_akun' => 'Beban Sosial',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => false,
            'keterangan' => 'Beban kegiatan sosial koperasi.',
        ],
        'beban_pendidikan' => [
            'kode_akun' => '505',
            'nama_akun' => 'Beban Pendidikan',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => false,
            'keterangan' => 'Beban pendidikan dan pengembangan anggota atau pengurus.',
        ],
        'beban_perawatan_aset' => [
            'kode_akun' => '506',
            'nama_akun' => 'Beban Perawatan Aset',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => true,
            'keterangan' => 'Beban servis/perawatan Mobil dan Printer koperasi.',
        ],
        'beban_atk_kantor' => [
            'kode_akun' => '507',
            'nama_akun' => 'Beban ATK dan Kantor',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => true,
            'keterangan' => 'Beban alat tulis kantor dan kebutuhan administrasi koperasi.',
        ],
        'beban_transportasi_operasional' => [
            'kode_akun' => '508',
            'nama_akun' => 'Beban Transportasi Operasional',
            'kategori' => 'beban',
            'posisi_saldo' => 'debit',
            'is_beban_operasional' => true,
            'keterangan' => 'Beban BBM, parkir, tol, dan transportasi kegiatan koperasi.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pemetaan transaksi ke identifier akun
    |--------------------------------------------------------------------------
    |
    | Bagian ini menjelaskan akun yang dipakai oleh setiap alur transaksi.
    | Nama jenis simpanan dinormalisasi menjadi slug sebelum dicocokkan.
    |
    */
    'postings' => [
        'penjualan' => [
            'kas' => 'kas',
            'piutang_potong_gaji' => 'piutang_anggota',
            'utang_konsinyasi' => 'utang_reseller_konsinyasi',
            'pendapatan' => 'pendapatan_penjualan',
            'hpp' => 'harga_pokok_penjualan',
            'persediaan' => 'persediaan_barang',
        ],
        'simpanan' => [
            'kas' => 'kas',
            'jenis' => [
                'pokok' => 'simpanan_pokok',
                'simpanan-pokok' => 'simpanan_pokok',
                'wajib' => 'simpanan_wajib',
                'simpanan-wajib' => 'simpanan_wajib',
                'simpanan-wajib-bulanan' => 'simpanan_wajib',
                'sukarela' => 'simpanan_sukarela',
                'simpanan-sukarela' => 'simpanan_sukarela',
                'hari-raya' => 'simpanan_khusus',
                'simpanan-hari-raya' => 'simpanan_khusus',
                'simpanan-khusus' => 'simpanan_khusus',
            ],
        ],
        'pinjaman' => [
            'piutang' => 'piutang_pinjaman',
            'kas' => 'kas',
            'pendapatan_jasa' => 'pendapatan_jasa_pinjaman',
            'pendapatan_admin' => 'pendapatan_administrasi',
        ],
        'konsinyasi' => [
            'utang_reseller' => 'utang_reseller_konsinyasi',
            'kas' => 'kas',
        ],
        'refund' => [
            'utang_anggota' => 'utang_refund_anggota',
            'piutang_potong_gaji' => 'piutang_anggota',
            'piutang_pinjaman' => 'piutang_pinjaman',
            'pendapatan_penjualan' => 'pendapatan_penjualan',
            'utang_konsinyasi' => 'utang_reseller_konsinyasi',
        ],
        'sewa_mobil' => [
            'pendapatan_diterima_dimuka' => 'pendapatan_diterima_dimuka_sewa_mobil',
            'pendapatan' => 'pendapatan_sewa_mobil',
        ],
        'sewa_printer' => [
            'utang_vendor' => 'utang_vendor_sewa_printer',
            'pendapatan_diterima_dimuka_margin' => 'pendapatan_diterima_dimuka_sewa_printer',
            'pendapatan_diterima_dimuka' => 'pendapatan_diterima_dimuka_sewa_printer',
            'pendapatan_dasar' => 'pendapatan_sewa_printer_dasar',
            'pendapatan_margin' => 'pendapatan_margin_sewa_printer',
        ],
        'keanggotaan' => [
            'simpanan_pokok' => 'simpanan_pokok',
            'simpanan_wajib' => 'simpanan_wajib',
            'simpanan_sukarela' => 'simpanan_sukarela',
            'utang_refund_anggota' => 'utang_refund_anggota',
            'piutang_pinjaman' => 'piutang_pinjaman',
            'piutang_anggota' => 'piutang_anggota',
        ],
    ],
];
