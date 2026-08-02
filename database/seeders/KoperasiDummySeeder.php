<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\AsetKoperasi;
use App\Models\BebanOperasional;
use App\Models\CicilanPinjaman;
use App\Models\DetailPenjualan;
use App\Models\DompetKoperasi;
use App\Models\JenisPinjaman;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\KategoriProduk;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PembayaranSewaMobil;
use App\Models\PembayaranSewaHardware;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\PengurusKoperasi;
use App\Models\Perusahaan;
use App\Models\Produk;
use App\Models\Reseller;
use App\Models\SewaMobil;
use App\Models\SewaHardware;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MutasiKasService;
use App\Services\AsetKoperasiService;
use App\Services\BebanOperasionalService;
use App\Services\KaryawanAccountService;
use App\Services\KeanggotaanLifecycleService;
use App\Services\JenisSimpananService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use App\Services\SewaMobilService;
use App\Services\SewaHardwareService;
use App\Services\SimpananManasukaService;
use App\Services\TransaksiReversalService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class KoperasiDummySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = Carbon::today();
            $awalBulanIni = $today->copy()->startOfMonth();
            $awalBulanLalu = $awalBulanIni->copy()->subMonth();
            $mutasiKasService = app(MutasiKasService::class);
            $masterDataService = app(MasterDataKoperasiService::class);
            $jenisSimpananService = app(JenisSimpananService::class);
            $simpananManasukaService = app(SimpananManasukaService::class);
            $pinjamanService = app(PinjamanKoperasiService::class);
            $asetKoperasiService = app(AsetKoperasiService::class);
            $karyawanAccountService = app(KaryawanAccountService::class);
            $sewaMobilService = app(SewaMobilService::class);
            $sewaHardwareService = app(SewaHardwareService::class);
            $bebanOperasionalService = app(BebanOperasionalService::class);
            $keanggotaanLifecycleService = app(KeanggotaanLifecycleService::class);
            $posCheckoutService = app(PosCheckoutService::class);
            $potongGajiService = app(PotongGajiBulananService::class);
            $reversalService = app(TransaksiReversalService::class);

            $keuangan = $this->seedUserDummy();

            $perusahaan = $this->seedPerusahaan();
            $potongGajiService->createDefaultGlobalPolicyIfMissing($keuangan->id);

            $karyawan = $this->seedKaryawan($masterDataService, $perusahaan);
            $jenisSimpanan = $this->seedJenisSimpanan($jenisSimpananService, $keuangan->id);
            $dompet = $this->seedDompetKoperasi();
            $anggota = $this->seedAnggota($karyawan, $masterDataService, $dompet);
            $kategori = $this->seedKategoriProduk();
            $reseller = $this->seedReseller();
            $produk = $this->seedProduk($kategori, $reseller);
            $this->seedJenisPinjaman();
            $this->seedPengurusKoperasi($anggota, $masterDataService);
            $this->seedAsetKoperasi($asetKoperasiService, $keuangan);
            $this->seedKaryawanAccounts($karyawanAccountService, $karyawan, $keuangan);

            $this->seedSimpanan($simpananManasukaService, $karyawan, $jenisSimpanan, $dompet, $keuangan, [
                [
                    'anggota' => 'agus',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'jumlah' => 150000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(18),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Titip simpanan manasuka untuk cadangan lebaran [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'dewi',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                    'metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
                    'jumlah' => 200000,
                    'tanggal' => $awalBulanIni->copy()->addDays(6),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan manasuka melalui Bank [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'dewi',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_PENARIKAN,
                    'metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
                    'jumlah' => 50000,
                    'tanggal' => $awalBulanIni->copy()->addDays(9),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Penarikan sebagian simpanan manasuka [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'fitri',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'jumlah' => 75000,
                    'tanggal' => $awalBulanIni->copy()->addDays(10),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Setoran untuk contoh saldo nol [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'fitri',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_PENARIKAN,
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'jumlah' => 75000,
                    'tanggal' => $awalBulanIni->copy()->addDays(11),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Penarikan penuh untuk contoh saldo nol [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'lilis',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'jumlah' => 120000,
                    'tanggal' => $awalBulanIni->copy()->addDays(12),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Setoran salah untuk contoh koreksi [dummy-koperasi-bita]',
                    'koreksi' => true,
                    'alasan_koreksi' => 'Dummy koreksi setoran Manasuka salah input.',
                ],
                [
                    'anggota' => 'nina',
                    'jenis' => 'manasuka',
                    'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                    'metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
                    'jumlah' => 125000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(9),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Saldo Manasuka untuk contoh refund penyelesaian keanggotaan SP-4 [dummy-koperasi-bita]',
                ],
            ]);

            $pinjaman = $this->seedPinjaman($pinjamanService, $karyawan, $dompet, $keuangan, [
                'budi_reguler' => [
                    'anggota' => 'budi',
                    'jumlah_pinjaman' => 1500000,
                    'bunga_persen' => 0,
                    'tenor_bulan' => 10,
                    'tanggal_pinjaman' => $awalBulanIni->copy()->addDays(3),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Pinjaman Reguler untuk biaya sekolah anak [dummy-koperasi-bita]',
                ],
                'rina_darurat' => [
                    'anggota' => 'rina',
                    'jumlah_pinjaman' => 800000,
                    'bunga_persen' => 0,
                    'tenor_bulan' => 6,
                    'tanggal_pinjaman' => $awalBulanLalu->copy()->addDays(12),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Pinjaman Darurat untuk servis motor kerja [dummy-koperasi-bita]',
                ],
                'agus_tunai' => [
                    'anggota' => 'agus',
                    'jumlah_pinjaman' => 600000,
                    'bunga_persen' => 0,
                    'tenor_bulan' => 6,
                    'tanggal_pinjaman' => $awalBulanLalu->copy()->addDays(4),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Pinjaman lama sebelum karyawan berhenti [dummy-koperasi-bita]',
                ],
            ]);
            $this->seedPinjamanLifecycleSp5($pinjamanService, $karyawan, $dompet, $keuangan, $awalBulanIni);

            $this->confirmDummyPayrollLimit(
                $potongGajiService,
                $karyawan['agus']->anggota()->firstOrFail(),
                Carbon::parse('2026-01-01'),
                200000,
                $keuangan,
                'Limit dummy untuk Simpanan Wajib Januari paid sebelum Agus keluar.'
            );

            $masterDataService->updateKaryawan($karyawan['agus'], [
                'nama' => $karyawan['agus']->nama,
                'email' => $karyawan['agus']->email,
                'telepon' => $karyawan['agus']->telepon,
                'jabatan' => $karyawan['agus']->jabatan,
                'status_kerja' => Karyawan::STATUS_BERHENTI,
                'tanggal_berhenti' => $awalBulanIni->copy()->subDay()->toDateString(),
            ]);

            $this->seedPotongGaji2C($potongGajiService, $karyawan, $keuangan, $awalBulanIni, $pinjaman);
            $potongGajiService->bulkGenerateLimitsForPeriod($awalBulanIni, $keuangan->id);
            $potongGajiService->bulkGenerateLimitsForPeriod($awalBulanIni->copy()->addMonth(), $keuangan->id);

            $this->seedPenjualan($posCheckoutService, $karyawan, $produk, $dompet, $keuangan, [
                [
                    'idempotency_key' => 'dummy-pos-umum-kas-1',
                    'tipe_pelanggan' => 'umum',
                    'metode_pembayaran' => 'tunai',
                    'diskon' => 2000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(10)->setTime(9, 15),
                    'dompet' => 'kas_operasional',
                    'items' => [
                        ['produk' => 'air_mineral', 'qty' => 12],
                        ['produk' => 'keripik_pisang', 'qty' => 3],
                    ],
                ],
                [
                    'idempotency_key' => 'dummy-pos-umum-qris-1',
                    'tipe_pelanggan' => 'umum',
                    'metode_pembayaran' => 'qris',
                    'diskon' => 0,
                    'tanggal' => $awalBulanLalu->copy()->addDays(18)->setTime(12, 30),
                    'dompet' => 'qris',
                    'items' => [
                        ['produk' => 'sabun_cuci', 'qty' => 2],
                        ['produk' => 'sambal_rumahan', 'qty' => 1],
                    ],
                ],
                [
                    'idempotency_key' => 'dummy-pos-siti-payroll-1',
                    'tipe_pelanggan' => 'anggota',
                    'anggota' => 'siti',
                    'metode_pembayaran' => 'potong_gaji',
                    'diskon' => 3000,
                    'tanggal' => $awalBulanIni->copy()->addDay()->setTime(10, 5),
                    'items' => [
                        ['produk' => 'buku_tulis', 'qty' => 5],
                        ['produk' => 'air_mineral', 'qty' => 6],
                    ],
                ],
                [
                    'idempotency_key' => 'dummy-pos-maya-transfer-1',
                    'tipe_pelanggan' => 'karyawan',
                    'karyawan' => 'maya',
                    'metode_pembayaran' => 'transfer_bank',
                    'diskon' => 5000,
                    'tanggal' => $awalBulanIni->copy()->addDays(3)->setTime(15, 20),
                    'dompet' => 'bank_bca',
                    'items' => [
                        ['produk' => 'beras_premium', 'qty' => 1],
                        ['produk' => 'minyak_goreng', 'qty' => 2],
                    ],
                ],
                [
                    'idempotency_key' => 'dummy-pos-umum-kas-2',
                    'tipe_pelanggan' => 'umum',
                    'metode_pembayaran' => 'tunai',
                    'diskon' => 0,
                    'tanggal' => $awalBulanIni->copy()->addDays(5)->setTime(11, 45),
                    'dompet' => 'kas_operasional',
                    'items' => [
                        ['produk' => 'brownies_kukus', 'qty' => 2],
                        ['produk' => 'roti_sisir', 'qty' => 1],
                        ['produk' => 'air_mineral', 'qty' => 4],
                    ],
                ],
                [
                    'idempotency_key' => 'dummy-pos-maya-qris-1',
                    'tipe_pelanggan' => 'karyawan',
                    'karyawan' => 'maya',
                    'metode_pembayaran' => 'qris',
                    'diskon' => 2500,
                    'tanggal' => $awalBulanIni->copy()->addDays(7)->setTime(16, 10),
                    'dompet' => 'qris',
                    'items' => [
                        ['produk' => 'kopi_mix', 'qty' => 2],
                        ['produk' => 'biskuit_cokelat', 'qty' => 3],
                        ['produk' => 'keripik_pisang', 'qty' => 2],
                    ],
                ],
                [
                    'idempotency_key' => 'dummy-pos-budi-payroll-next',
                    'tipe_pelanggan' => 'anggota',
                    'anggota' => 'budi',
                    'metode_pembayaran' => 'potong_gaji',
                    'diskon' => 4000,
                    'tanggal' => $awalBulanIni->copy()->addMonth()->addDays(9)->setTime(13, 0),
                    'items' => [
                        ['produk' => 'gula_pasir', 'qty' => 2],
                        ['produk' => 'minyak_goreng', 'qty' => 1],
                        ['produk' => 'roti_sisir', 'qty' => 2],
                    ],
                ],
            ]);

            $this->seedStage2EExamples(
                $potongGajiService,
                $posCheckoutService,
                $reversalService,
                $karyawan,
                $produk,
                $dompet,
                $keuangan,
                $awalBulanIni
            );

            $this->seedSewaMobil($sewaMobilService, $karyawan, $dompet, $keuangan, $awalBulanIni);
            $this->seedSewaHardware($sewaHardwareService, $karyawan, $dompet, $keuangan, $awalBulanIni);
            $this->seedBebanOperasional($bebanOperasionalService, $dompet, $keuangan, $awalBulanIni);
            $this->seedStage3FExamples(
                $keanggotaanLifecycleService,
                $masterDataService,
                $potongGajiService,
                $karyawan,
                $dompet,
                $keuangan,
                $awalBulanIni
            );
        });
    }

    private function seedUserDummy(): User
    {
        $finance = User::updateOrCreate(
            ['email' => 'keuangan@kbsm.test'],
            [
                'name' => 'Admin Keuangan KBSM',
                'password' => Hash::make('Kbsm12345!'),
                'role' => 'admin',
                'karyawan_id' => null,
                'is_active' => true,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir@kbsm.test'],
            [
                'name' => 'Kasir KBSM',
                'password' => Hash::make('Kbsm12345!'),
                'role' => 'kasir',
                'karyawan_id' => null,
                'is_active' => true,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'email_verified_at' => now(),
            ]
        );

        return $finance;
    }

    private function seedPerusahaan(): array
    {
        $rows = [
            'BEE' => 'Bita Enarcon Engineering',
            'BBS' => 'Bita Bina Semesta',
            'BKM' => 'Bamko Karsa Mandiri',
        ];

        $result = [];
        foreach ($rows as $kode => $nama) {
            $result[$kode] = Perusahaan::query()->updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama]
            );
        }

        return $result;
    }

    private function seedKaryawan(MasterDataKoperasiService $service, array $perusahaan): array
    {
        $rows = [
            'andi' => [
                'nama' => 'Andi Saputra',
                'email' => 'andi.saputra@bita.test',
                'telepon' => '081230000101',
                'jabatan' => 'Kasir Toko',
            ],
            'siti' => [
                'nama' => 'Siti Rahmawati',
                'email' => 'siti.rahmawati@bita.test',
                'telepon' => '081230000102',
                'jabatan' => 'Admin Koperasi',
            ],
            'budi' => [
                'nama' => 'Budi Santoso',
                'email' => 'budi.santoso@bita.test',
                'telepon' => '081230000103',
                'jabatan' => 'Anggota Gudang',
            ],
            'rina' => [
                'nama' => 'Rina Marlina',
                'email' => 'rina.marlina@bita.test',
                'telepon' => '081230000104',
                'jabatan' => 'Anggota Produksi',
            ],
            'agus' => [
                'nama' => 'Agus Setiawan',
                'email' => 'agus.setiawan@bita.test',
                'telepon' => '081230000105',
                'jabatan' => 'Anggota Harian',
            ],
            'dewi' => [
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lestari@bita.test',
                'telepon' => '081230000106',
                'jabatan' => 'Bendahara Unit',
            ],
            'fitri' => [
                'nama' => 'Fitri Handayani',
                'email' => 'fitri.handayani@bita.test',
                'telepon' => '081230000107',
                'jabatan' => 'Staf Persediaan',
            ],
            'lilis' => [
                'nama' => 'Lilis Suryani',
                'email' => 'lilis.suryani@bita.test',
                'telepon' => '081230000108',
                'jabatan' => 'Supervisor Toko',
            ],
            'maya' => [
                'nama' => 'Maya Pratiwi',
                'email' => 'maya.pratiwi@bita.test',
                'telepon' => '081230000109',
                'jabatan' => 'Staf Umum',
            ],
            'nina' => [
                'nama' => 'Nina Kusumawati',
                'email' => 'nina.kusumawati@bita.test',
                'telepon' => '081230000110',
                'jabatan' => 'Staf Administrasi',
            ],
            'wawan_sp5_draft' => [
                'nama' => 'Wawan Prasetyo',
                'email' => 'wawan.prasetyo@bita.test',
                'telepon' => '081230000111',
                'jabatan' => 'Staf Gudang',
            ],
            'yuni_sp5_diajukan' => [
                'nama' => 'Yuni Kartika',
                'email' => 'yuni.kartika@bita.test',
                'telepon' => '081230000112',
                'jabatan' => 'Staf Finance',
            ],
            'farhan_sp5_disetujui' => [
                'nama' => 'Farhan Maulana',
                'email' => 'farhan.maulana@bita.test',
                'telepon' => '081230000113',
                'jabatan' => 'Staf Operasional',
            ],
            'lina_sp5_ditolak' => [
                'nama' => 'Lina Permatasari',
                'email' => 'lina.permatasari@bita.test',
                'telepon' => '081230000114',
                'jabatan' => 'Staf HR',
            ],
            'toni_sp5_dibatalkan' => [
                'nama' => 'Toni Wijaya',
                'email' => 'toni.wijaya@bita.test',
                'telepon' => '081230000115',
                'jabatan' => 'Staf Produksi',
            ],
            'vera_sp5_aktif' => [
                'nama' => 'Vera Oktaviani',
                'email' => 'vera.oktaviani@bita.test',
                'telepon' => '081230000116',
                'jabatan' => 'Staf Procurement',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $data = $row + [
                'status_kerja' => Karyawan::STATUS_AKTIF,
                'tanggal_berhenti' => null,
                'perusahaan_id' => match ($key) {
                    'dewi', 'fitri', 'nina', 'lina_sp5_ditolak' => $perusahaan['BBS']->id,
                    'budi', 'rina', 'maya', 'farhan_sp5_disetujui', 'toni_sp5_dibatalkan' => $perusahaan['BKM']->id,
                    default => $perusahaan['BEE']->id,
                },
            ];
            $existing = Karyawan::query()->where('email', $row['email'])->first();

            $result[$key] = $existing
                ? $service->updateKaryawan($existing, $data)
                : $service->createKaryawan($data);
        }

        return $result;
    }

    private function seedAnggota(array $karyawan, MasterDataKoperasiService $service, array $dompet): array
    {
        $rows = [
            'andi' => ['tanggal_bergabung' => '2026-01-05', 'alamat' => 'Jl. Dummy Melati No. 1', 'plafon_pinjaman' => 3000000],
            'siti' => ['tanggal_bergabung' => '2026-01-06', 'alamat' => 'Jl. Dummy Melati No. 2', 'plafon_pinjaman' => 5000000],
            'budi' => ['tanggal_bergabung' => '2026-01-07', 'alamat' => 'Jl. Dummy Kenanga No. 3', 'plafon_pinjaman' => 2500000],
            'rina' => ['tanggal_bergabung' => '2026-01-08', 'alamat' => 'Jl. Dummy Kenanga No. 4', 'plafon_pinjaman' => 2000000],
            'agus' => ['tanggal_bergabung' => '2026-01-09', 'alamat' => 'Jl. Dummy Mawar No. 5', 'plafon_pinjaman' => 1500000],
            'dewi' => [
                'tanggal_bergabung' => '2026-01-10',
                'alamat' => 'Jl. Dummy Mawar No. 6',
                'plafon_pinjaman' => 5000000,
                'simpanan_wajib_metode_pembayaran' => Simpanan::METODE_TUNAI,
                'simpanan_wajib_dompet_id' => $dompet['kas_operasional']->id,
            ],
            'fitri' => [
                'tanggal_bergabung' => '2026-01-11',
                'alamat' => 'Jl. Dummy Anggrek No. 7',
                'plafon_pinjaman' => 2500000,
                'simpanan_wajib_metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
                'simpanan_wajib_dompet_id' => $dompet['bank_bca']->id,
            ],
            'lilis' => ['tanggal_bergabung' => '2026-01-12', 'alamat' => 'Jl. Dummy Anggrek No. 8', 'plafon_pinjaman' => 3500000],
            'nina' => ['tanggal_bergabung' => '2026-01-13', 'alamat' => 'Jl. Dummy Cendana No. 9', 'plafon_pinjaman' => 1500000],
            'wawan_sp5_draft' => ['tanggal_bergabung' => '2026-01-14', 'alamat' => 'Jl. Dummy SP5 No. 1', 'plafon_pinjaman' => 2500000],
            'yuni_sp5_diajukan' => ['tanggal_bergabung' => '2026-01-15', 'alamat' => 'Jl. Dummy SP5 No. 2', 'plafon_pinjaman' => 3000000],
            'farhan_sp5_disetujui' => ['tanggal_bergabung' => '2026-01-16', 'alamat' => 'Jl. Dummy SP5 No. 3', 'plafon_pinjaman' => 3500000],
            'lina_sp5_ditolak' => ['tanggal_bergabung' => '2026-01-17', 'alamat' => 'Jl. Dummy SP5 No. 4', 'plafon_pinjaman' => 2500000],
            'toni_sp5_dibatalkan' => ['tanggal_bergabung' => '2026-01-18', 'alamat' => 'Jl. Dummy SP5 No. 5', 'plafon_pinjaman' => 2500000],
            'vera_sp5_aktif' => ['tanggal_bergabung' => '2026-01-19', 'alamat' => 'Jl. Dummy SP5 No. 6', 'plafon_pinjaman' => 4000000],
        ];

        $result = [];

        foreach ($rows as $key => $row) {
            $anggota = $karyawan[$key]->anggota()->first();

            if (! $anggota) {
                $anggota = $service->createAnggota($row + ['karyawan_id' => $karyawan[$key]->id]);
            } else {
                if ($anggota->status !== Anggota::STATUS_AKTIF) {
                    $service->activateAnggota($anggota);
                }

                $anggota = $service->updateAnggota($anggota, $row);
            }

            $result[$key] = $anggota;
        }

        return $result;
    }

    private function seedKategoriProduk(): array
    {
        $rows = [
            'sembako' => [
                'nama_kategori' => 'Sembako Harian',
                'deskripsi' => 'Produk kebutuhan pokok yang paling sering bergerak.',
            ],
            'minuman' => [
                'nama_kategori' => 'Minuman',
                'deskripsi' => 'Air mineral, kopi sachet, dan minuman praktis.',
            ],
            'snack' => [
                'nama_kategori' => 'Snack',
                'deskripsi' => 'Camilan kemasan dan titipan reseller yang cepat laku.',
            ],
            'atk' => [
                'nama_kategori' => 'ATK',
                'deskripsi' => 'Perlengkapan tulis kecil untuk kebutuhan anggota.',
            ],
            'kebersihan' => [
                'nama_kategori' => 'Kebersihan',
                'deskripsi' => 'Produk pembersih rumah tangga dan toko.',
            ],
            'titipan_umkm' => [
                'nama_kategori' => 'Titipan UMKM',
                'deskripsi' => 'Produk konsinyasi dari reseller sekitar koperasi.',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $result[$key] = KategoriProduk::firstOrCreate(
                ['nama_kategori' => $row['nama_kategori']],
                $row
            );
        }

        return $result;
    }

    private function seedReseller(): array
    {
        $rows = [
            'dapur_bu_rina' => [
                'nama_reseller' => 'Dapur Bu Rina',
                'telepon' => '081310000201',
                'alamat' => 'Jl. Melati No. 12, dekat kantor koperasi',
            ],
            'roti_kampung' => [
                'nama_reseller' => 'Roti Kampung Sejahtera',
                'telepon' => '081310000202',
                'alamat' => 'Jl. Kenanga No. 8, Pasar Pagi',
            ],
            'keripik_nusantara' => [
                'nama_reseller' => 'Keripik Nusantara',
                'telepon' => '081310000203',
                'alamat' => 'Jl. Flamboyan No. 4, Sentra UMKM',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $result[$key] = Reseller::firstOrCreate(
                ['nama_reseller' => $row['nama_reseller']],
                $row
            );
        }

        return $result;
    }

    private function seedProduk(array $kategori, array $reseller): array
    {
        $rows = [
            'beras_premium' => [
                'nama_produk' => 'Beras Premium BITA 5kg',
                'kategori_id' => $kategori['sembako']->id,
                'harga_beli' => 69000,
                'harga_jual' => 76000,
                'stok' => 35,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'beras-premium.svg',
            ],
            'gula_pasir' => [
                'nama_produk' => 'Gula Pasir Kristal 1kg',
                'kategori_id' => $kategori['sembako']->id,
                'harga_beli' => 15500,
                'harga_jual' => 18000,
                'stok' => 50,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'gula-pasir.svg',
            ],
            'minyak_goreng' => [
                'nama_produk' => 'Minyak Goreng Hemat 1L',
                'kategori_id' => $kategori['sembako']->id,
                'harga_beli' => 17000,
                'harga_jual' => 19500,
                'stok' => 40,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'minyak-goreng.svg',
            ],
            'air_mineral' => [
                'nama_produk' => 'Air Mineral 600ml',
                'kategori_id' => $kategori['minuman']->id,
                'harga_beli' => 2500,
                'harga_jual' => 4000,
                'stok' => 80,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'air-mineral.svg',
            ],
            'kopi_mix' => [
                'nama_produk' => 'Kopi Mix 10 Sachet',
                'kategori_id' => $kategori['minuman']->id,
                'harga_beli' => 12000,
                'harga_jual' => 14500,
                'stok' => 45,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'kopi-mix.svg',
            ],
            'biskuit_cokelat' => [
                'nama_produk' => 'Biskuit Cokelat Keluarga',
                'kategori_id' => $kategori['snack']->id,
                'harga_beli' => 8500,
                'harga_jual' => 11000,
                'stok' => 30,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'biskuit-cokelat.svg',
            ],
            'buku_tulis' => [
                'nama_produk' => 'Buku Tulis 38 Lembar',
                'kategori_id' => $kategori['atk']->id,
                'harga_beli' => 3500,
                'harga_jual' => 5000,
                'stok' => 60,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'buku-tulis.svg',
            ],
            'sabun_cuci' => [
                'nama_produk' => 'Sabun Cuci Piring 800ml',
                'kategori_id' => $kategori['kebersihan']->id,
                'harga_beli' => 11000,
                'harga_jual' => 14000,
                'stok' => 25,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'sabun-cuci-piring.svg',
            ],
            'brownies_kukus' => [
                'nama_produk' => 'Brownies Kukus Cokelat',
                'kategori_id' => $kategori['titipan_umkm']->id,
                'harga_beli' => 0,
                'harga_jual' => 18000,
                'stok' => 12,
                'konsinyasi' => true,
                'reseller_id' => $reseller['dapur_bu_rina']->id,
                'harga_setor' => 13000,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'brownies-cokelat.svg',
            ],
            'roti_sisir' => [
                'nama_produk' => 'Roti Sisir Keju',
                'kategori_id' => $kategori['titipan_umkm']->id,
                'harga_beli' => 0,
                'harga_jual' => 15000,
                'stok' => 15,
                'konsinyasi' => true,
                'reseller_id' => $reseller['roti_kampung']->id,
                'harga_setor' => 10500,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'roti-sisir-keju.svg',
            ],
            'keripik_pisang' => [
                'nama_produk' => 'Keripik Pisang Original',
                'kategori_id' => $kategori['titipan_umkm']->id,
                'harga_beli' => 0,
                'harga_jual' => 12000,
                'stok' => 20,
                'konsinyasi' => true,
                'reseller_id' => $reseller['keripik_nusantara']->id,
                'harga_setor' => 8500,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'keripik-pisang.svg',
            ],
            'sambal_rumahan' => [
                'nama_produk' => 'Sambal Botol Rumahan 200ml',
                'kategori_id' => $kategori['titipan_umkm']->id,
                'harga_beli' => 0,
                'harga_jual' => 22000,
                'stok' => 10,
                'konsinyasi' => true,
                'reseller_id' => $reseller['dapur_bu_rina']->id,
                'harga_setor' => 16000,
                'foto' => Produk::DEMO_PHOTO_PREFIX . 'sambal-botol.svg',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $lookup = [
                'nama_produk' => $row['nama_produk'],
                'kategori_id' => $row['kategori_id'],
                'konsinyasi' => $row['konsinyasi'],
            ];

            if ($row['konsinyasi']) {
                $lookup['reseller_id'] = $row['reseller_id'];
            }

            $produk = Produk::firstOrCreate($lookup, $row);

            if (empty($produk->foto) && isset($row['foto'])) {
                $produk->update(['foto' => $row['foto']]);
            }

            $result[$key] = $produk->fresh();
        }

        return $result;
    }

    private function seedDompetKoperasi(): array
    {
        $kasAkunId = \App\Models\Akun::query()->where('kode_akun', '101')->value('id');
        $bankAkunId = \App\Models\Akun::query()->where('kode_akun', '102')->value('id');

        $rows = [
            'kas_operasional' => [
                'nama_dompet' => 'Kas Operasional',
                'akun_id' => $kasAkunId,
                'jenis_dompet' => 'kas',
                'is_default_penerimaan_payroll' => false,
                'saldo_awal' => 12500000,
            ],
            'bank_bca' => [
                'nama_dompet' => 'Bank BCA Koperasi',
                'akun_id' => $bankAkunId,
                'jenis_dompet' => 'bank',
                'is_default_penerimaan_payroll' => true,
                'saldo_awal' => 25000000,
            ],
            'qris' => [
                'nama_dompet' => 'QRIS Koperasi',
                'akun_id' => $bankAkunId,
                'jenis_dompet' => 'bank',
                'is_default_penerimaan_payroll' => false,
                'saldo_awal' => 2500000,
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            if ($row['is_default_penerimaan_payroll']) {
                DompetKoperasi::query()
                    ->where('is_default_penerimaan_payroll', true)
                    ->get()
                    ->each(fn (DompetKoperasi $existing) => $existing->update(['is_default_penerimaan_payroll' => false]));
            }

            $dompet = DompetKoperasi::firstOrCreate(
                ['nama_dompet' => $row['nama_dompet']],
                [
                    'saldo' => $row['saldo_awal'],
                    'akun_id' => $row['akun_id'],
                    'jenis_dompet' => $row['jenis_dompet'],
                    'is_default_penerimaan_payroll' => $row['is_default_penerimaan_payroll'],
                ]
            );

            if (
                (int) $dompet->akun_id !== (int) $row['akun_id']
                || $dompet->jenis_dompet !== $row['jenis_dompet']
                || (bool) $dompet->is_default_penerimaan_payroll !== (bool) $row['is_default_penerimaan_payroll']
            ) {
                $dompet->update([
                    'akun_id' => $row['akun_id'],
                    'jenis_dompet' => $row['jenis_dompet'],
                    'is_default_penerimaan_payroll' => $row['is_default_penerimaan_payroll'],
                ]);
            }

            $result[$key] = $dompet->fresh('akun');
        }

        return $result;
    }

    private function seedJenisSimpanan(JenisSimpananService $service, int $userId): array
    {
        $akunIds = [
            'wajib' => $this->akunId('simpanan_wajib'),
            'manasuka' => $this->akunId('simpanan_manasuka'),
        ];

        JenisSimpanan::query()
            ->where(function ($query): void {
                $query->where('kategori', JenisSimpanan::KATEGORI_POKOK)
                    ->orWhere('kode', JenisSimpanan::KODE_SIMPANAN_POKOK);
            })
            ->update([
                'aktif' => false,
                'interval_bulan' => null,
                'keterangan' => 'Legacy SP-7: fungsi satu kali digantikan oleh Simpanan Wajib final.',
                'updated_by' => $userId,
            ]);

        $rows = [
            'wajib' => [
                'akun_id' => $akunIds['wajib'],
                'kode' => JenisSimpanan::KODE_SIMPANAN_WAJIB,
                'kategori' => JenisSimpanan::KATEGORI_WAJIB,
                'nama_jenis' => 'Simpanan Wajib',
                'aktif' => true,
                'nominal_default' => 10000,
                'interval_bulan' => null,
                'berlaku_mulai' => '2026-01-01',
                'keterangan' => 'Dibayar Rp10.000 satu kali setiap siklus keanggotaan.',
                'alasan_perubahan' => 'Setup dummy SP-7 Simpanan Wajib final satu kali per siklus.',
            ],
            'manasuka' => [
                'akun_id' => $akunIds['manasuka'],
                'kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA,
                'kategori' => JenisSimpanan::KATEGORI_MANASUKA,
                'nama_jenis' => 'Simpanan Manasuka',
                'aktif' => true,
                'nominal_default' => 0,
                'interval_bulan' => null,
                'berlaku_mulai' => '2026-01-01',
                'keterangan' => 'Tabungan pilihan Anggota yang dapat disetor dan ditarik.',
                'alasan_perubahan' => 'Setup dummy Master Simpanan Manasuka final.',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $existing = JenisSimpanan::query()
                ->where('kode', $row['kode'])
                ->first();

            if ($existing) {
                $result[$key] = $service->update($existing, $row, $userId);
            } else {
                $result[$key] = $service->create($row, $userId);
            }
        }

        return $result;
    }

    private function akunId(string $accountKey): int
    {
        $accountCode = config("account_map.accounts.{$accountKey}.kode_akun");
        $akunId = Akun::query()->where('kode_akun', $accountCode)->value('id');

        if (! $akunId) {
            throw new \RuntimeException("Akun sistem {$accountKey} belum tersedia.");
        }

        return (int) $akunId;
    }

    private function seedJenisPinjaman(): void
    {
        $rows = [
            [
                'nama_pinjaman' => 'Pinjaman Reguler',
                'bunga_persen' => 0,
                'tenor_bulan' => 12,
                'keterangan' => 'Pinjaman umum untuk kebutuhan rumah tangga dan pendidikan.',
            ],
            [
                'nama_pinjaman' => 'Pinjaman Darurat',
                'bunga_persen' => 0,
                'tenor_bulan' => 6,
                'keterangan' => 'Pinjaman cepat untuk kebutuhan mendesak anggota.',
            ],
            [
                'nama_pinjaman' => 'Pinjaman Pendidikan',
                'bunga_persen' => 0,
                'tenor_bulan' => 10,
                'keterangan' => 'Pinjaman terjadwal untuk uang sekolah dan kursus.',
            ],
            [
                'nama_pinjaman' => 'Pinjaman Multiguna',
                'bunga_persen' => 0,
                'tenor_bulan' => 8,
                'keterangan' => 'Pinjaman fleksibel untuk kebutuhan campuran anggota.',
            ],
        ];

        foreach ($rows as $row) {
            JenisPinjaman::firstOrCreate(
                ['nama_pinjaman' => $row['nama_pinjaman']],
                $row
            );
        }
    }

    private function seedPengurusKoperasi(array $anggota, MasterDataKoperasiService $service): void
    {
        if (! Schema::hasTable('pengurus_koperasi')) {
            return;
        }

        $rows = [
            [
                'anggota' => 'dewi',
                'jabatan' => 'Ketua Pengurus',
            ],
            [
                'anggota' => 'siti',
                'jabatan' => 'Sekretaris',
            ],
            [
                'anggota' => 'budi',
                'jabatan' => 'Bendahara',
            ],
        ];

        foreach ($rows as $row) {
            $existing = PengurusKoperasi::query()
                ->where('anggota_id', $anggota[$row['anggota']]->id)
                ->where('jabatan', $row['jabatan'])
                ->first();

            if (! $existing) {
                $service->createPengurus([
                    'anggota_id' => $anggota[$row['anggota']]->id,
                    'jabatan' => $row['jabatan'],
                ]);
            } elseif ($existing->status !== PengurusKoperasi::STATUS_AKTIF) {
                $service->activatePengurus($existing);
            }
        }
    }

    private function seedAsetKoperasi(AsetKoperasiService $service, User $keuangan): void
    {
        if (! Schema::hasTable('aset_koperasi') || AsetKoperasi::query()->exists()) {
            return;
        }

        // Master Mobil dan Master Printer sengaja tidak dibuat di demo final:
        // Sewa Mobil dan Sewa Hardware memakai snapshot vendor eksternal, bukan aset koperasi.
    }

    private function applyDummyAsetStatus(
        AsetKoperasiService $service,
        AsetKoperasi $aset,
        string $status,
        User $keuangan
    ): void {
        if ($status === AsetKoperasi::STATUS_TERSEDIA) {
            return;
        }

        if ($status === AsetKoperasi::STATUS_NONAKTIF) {
            $service->nonaktifkan($aset, $keuangan->id);

            return;
        }

        $service->updateStatus($aset, $status, $keuangan->id);
    }

    /**
     * @return array<string, User>
     */
    private function seedKaryawanAccounts(KaryawanAccountService $service, array $karyawan, User $keuangan): array
    {
        $users = [];

        foreach ($karyawan as $key => $employee) {
            $existing = $employee->user()->first();
            $users[$key] = $existing ?: $service->createAccount($employee, 'karyawan123', 'karyawan', $keuangan->id);
        }

        return $users;
    }

    private function seedSewaMobil(
        SewaMobilService $service,
        array $karyawan,
        array $dompet,
        User $keuangan,
        Carbon $awalBulanIni
    ): void {
        if (! Schema::hasTable('sewa_mobil') || SewaMobil::query()->exists()) {
            return;
        }

        $pengurus = PengurusKoperasi::query()
            ->aktif()
            ->with('anggota.karyawan')
            ->firstOrFail();

        $base = $awalBulanIni->copy()->day(10);

        $draft = $service->createDraft($this->sewaMobilPayload($karyawan['maya'], $base->copy(), [
            'nama_kegiatan' => 'Survey Lokasi Vendor',
            'lokasi_kegiatan' => 'Cikarang',
            'tanggal_selesai' => $base->copy()->addDays(2)->toDateString(),
            'plat_nomor_snapshot' => null,
            'total_harga_vendor' => 1200000,
            'total_markup' => 225000,
            'keterangan' => 'Contoh draft sewa mobil [dummy-koperasi-bita]',
        ]), $keuangan->id);

        $diajukan = $service->createDraft($this->sewaMobilPayload($karyawan['fitri'], $base->copy()->addDays(3), [
            'nama_kegiatan' => 'Pengambilan Dokumen',
            'lokasi_kegiatan' => 'Bekasi',
            'plat_nomor_snapshot' => 'B 7001 KBS',
        ]), $keuangan->id);
        $service->submit($diajukan, $keuangan->id);

        $approvedUnpaid = $service->createDraft($this->sewaMobilPayload($karyawan['dewi'], $base->copy()->addDays(4), [
            'nama_kegiatan' => 'Kunjungan Supplier',
            'lokasi_kegiatan' => 'Karawang',
            'plat_nomor_snapshot' => 'B 7002 KBS',
        ]), $keuangan->id);
        $approvedUnpaid = $service->submit($approvedUnpaid, $keuangan->id);
        $service->approve($approvedUnpaid, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $keuangan->id);

        $paid = $service->createDraft($this->sewaMobilPayload($karyawan['siti'], $base->copy()->addDays(5), [
            'nama_kegiatan' => 'Kegiatan CSR',
            'lokasi_kegiatan' => 'Bogor',
            'plat_nomor_snapshot' => 'B 7003 KBS',
        ]), $keuangan->id);
        $paid = $service->submit($paid, $keuangan->id);
        $paid = $service->approve($paid, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $keuangan->id);
        $service->pay($paid, $this->sewaMobilPaymentPayload(
            $paid,
            $dompet['kas_operasional'],
            $dompet['kas_operasional'],
            PembayaranSewaMobil::METODE_TUNAI,
            PembayaranSewaMobil::METODE_TUNAI,
            $base->copy()->addDays(5)->setTime(9, 0)
        ), $keuangan->id);

        $selesai = $service->createDraft($this->sewaMobilPayload($karyawan['lilis'], $base->copy()->addDays(6), [
            'nama_kegiatan' => 'Distribusi Bantuan',
            'lokasi_kegiatan' => 'Jakarta',
            'tanggal_selesai' => $base->copy()->addDays(7)->toDateString(),
            'plat_nomor_snapshot' => 'B 7004 KBS',
            'total_harga_vendor' => 1500000,
            'total_markup' => 300000,
        ]), $keuangan->id);
        $selesai = $service->submit($selesai, $keuangan->id);
        $selesai = $service->approve($selesai, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $keuangan->id);
        $selesai = $service->pay($selesai, $this->sewaMobilPaymentPayload(
            $selesai,
            $dompet['bank_bca'],
            $dompet['bank_bca'],
            PembayaranSewaMobil::METODE_TRANSFER_BANK,
            PembayaranSewaMobil::METODE_TRANSFER_BANK,
            $base->copy()->addDays(6)->setTime(13, 0)
        ), $keuangan->id);
        $selesai = $service->start($selesai, $keuangan->id);
        $service->complete($selesai, $keuangan->id);

        $ditolak = $service->createDraft($this->sewaMobilPayload($karyawan['rina'], $base->copy()->addDays(8), [
            'nama_kegiatan' => 'Permohonan Jadwal Bentrok',
            'lokasi_kegiatan' => 'Tangerang',
            'plat_nomor_snapshot' => 'B 7005 KBS',
        ]), $keuangan->id);
        $ditolak = $service->submit($ditolak, $keuangan->id);
        $service->reject($ditolak, 'Jadwal tidak disetujui oleh Pengurus di luar aplikasi [dummy].', $keuangan->id);

        $refunded = $service->createDraft($this->sewaMobilPayload($karyawan['andi'], $base->copy()->addDays(9), [
            'nama_kegiatan' => 'Rapat Koordinasi Proyek',
            'lokasi_kegiatan' => 'Bandung',
            'plat_nomor_snapshot' => 'B 7006 KBS',
        ]), $keuangan->id);
        $refunded = $service->submit($refunded, $keuangan->id);
        $refunded = $service->approve($refunded, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $keuangan->id);
        $refunded = $service->pay($refunded, $this->sewaMobilPaymentPayload(
            $refunded,
            $dompet['kas_operasional'],
            $dompet['kas_operasional'],
            PembayaranSewaMobil::METODE_TUNAI,
            PembayaranSewaMobil::METODE_TUNAI,
            $base->copy()->addDays(9)->setTime(10, 0)
        ), $keuangan->id);
        $service->cancelByFinance($refunded, 'Kegiatan dibatalkan sebelum berjalan dan dana direfund penuh [dummy].', $keuangan->id);

        $running = $service->createDraft($this->sewaMobilPayload($karyawan['budi'], $base->copy()->addDays(10), [
            'nama_kegiatan' => 'Kunjungan Audit Lapangan',
            'lokasi_kegiatan' => 'Purwakarta',
            'tanggal_selesai' => $base->copy()->addDays(11)->toDateString(),
            'plat_nomor_snapshot' => 'B 7007 KBS',
        ]), $keuangan->id);
        $running = $service->submit($running, $keuangan->id);
        $running = $service->approve($running, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $keuangan->id);
        $running = $service->pay($running, $this->sewaMobilPaymentPayload(
            $running,
            $dompet['bank_bca'],
            $dompet['bank_bca'],
            PembayaranSewaMobil::METODE_TRANSFER_BANK,
            PembayaranSewaMobil::METODE_TRANSFER_BANK,
            $base->copy()->addDays(10)->setTime(15, 0)
        ), $keuangan->id);
        $service->start($running, $keuangan->id);

        $dibatalkan = $service->createDraft($this->sewaMobilPayload($karyawan['maya'], $base->copy()->addDays(12), [
            'nama_kegiatan' => 'Rencana Kunjungan Vendor',
            'lokasi_kegiatan' => 'Serang',
            'plat_nomor_snapshot' => null,
        ]), $keuangan->id);
        $service->cancelByFinance($dibatalkan, 'Draft dibatalkan sebelum diajukan [dummy].', $keuangan->id);
    }

    private function sewaMobilPayload(Karyawan $karyawan, Carbon $tanggal, array $overrides = []): array
    {
        return array_merge([
            'karyawan_id' => $karyawan->id,
            'nama_kegiatan' => 'Kegiatan Operasional',
            'lokasi_kegiatan' => 'Area Jabodetabek',
            'tanggal_mulai' => $tanggal->toDateString(),
            'tanggal_selesai' => $tanggal->toDateString(),
            'vendor_nama' => 'CV Rental Mobil Nusantara',
            'vendor_kontak' => '0812-7000-0000',
            'vendor_alamat' => 'Jl. Raya Rental No. 10, Jakarta',
            'jenis_kendaraan' => 'MPV',
            'merek_kendaraan' => 'Toyota',
            'model_kendaraan' => 'Innova Reborn',
            'plat_nomor_snapshot' => 'B 7000 KBS',
            'tahun_kendaraan' => 2022,
            'warna_kendaraan' => 'Hitam',
            'keterangan_kendaraan' => 'Kendaraan vendor dengan sopir [dummy-koperasi-bita]',
            'total_harga_vendor' => 1000000,
            'total_markup' => 150000,
            'keterangan' => 'Data dummy sewa mobil [dummy-koperasi-bita]',
        ], $overrides);
    }

    private function sewaMobilPaymentPayload(
        SewaMobil $sewaMobil,
        DompetKoperasi $dompetPenerimaan,
        DompetKoperasi $dompetVendor,
        string $metodePenerimaan,
        string $metodePembayaranVendor,
        Carbon $paidAt
    ): array {
        return [
            'metode_penerimaan' => $metodePenerimaan,
            'dompet_penerimaan_id' => $dompetPenerimaan->id,
            'jumlah_diterima' => $sewaMobil->total_tagihan_perusahaan,
            'metode_pembayaran_vendor' => $metodePembayaranVendor,
            'dompet_vendor_id' => $dompetVendor->id,
            'jumlah_bayar_vendor' => $sewaMobil->total_harga_vendor,
            'paid_at' => $paidAt,
        ];
    }

    private function seedSewaHardware(
        SewaHardwareService $service,
        array $karyawan,
        array $dompet,
        User $keuangan,
        Carbon $awalBulanIni
    ): void {
        if (! Schema::hasTable('sewa_hardware') || SewaHardware::query()->exists()) {
            return;
        }

        $draft = $service->createDraft($this->sewaHardwarePayload($karyawan['maya'], $awalBulanIni->copy()->addDays(40), [
            'details' => [
                [
                    'jenis_hardware' => 'printer',
                    'nama_model_hardware' => 'Epson EcoTank L3210',
                    'spesifikasi_kebutuhan' => 'Printer warna untuk administrasi proyek',
                    'kuantitas' => 2,
                    'harga_vendor_per_unit' => 1000000,
                ],
                [
                    'jenis_hardware' => 'laptop',
                    'nama_model_hardware' => 'Lenovo ThinkPad T14',
                    'spesifikasi_kebutuhan' => 'Laptop presentasi dan administrasi lapangan',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 800000,
                ],
            ],
            'keterangan' => 'Contoh draft multi-hardware [dummy-koperasi-bita]',
        ]), $keuangan->id);

        $confirmed = $service->createDraft($this->sewaHardwarePayload($karyawan['fitri'], $awalBulanIni->copy()->addDays(41), [
            'details' => [
                [
                    'jenis_hardware' => 'kamera',
                    'nama_model_hardware' => 'Sony Alpha A6400',
                    'spesifikasi_kebutuhan' => 'Dokumentasi site visit proyek',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 750000,
                ],
            ],
            'keterangan' => 'Contoh confirmed unpaid [dummy-koperasi-bita]',
        ]), $keuangan->id);
        $service->confirm($confirmed, $keuangan->id);

        $paid = $service->createDraft($this->sewaHardwarePayload($karyawan['dewi'], $awalBulanIni->copy()->addDays(45), [
            'details' => [
                [
                    'jenis_hardware' => 'lainnya',
                    'nama_model_hardware' => 'Portable Projector HDMI',
                    'spesifikasi_kebutuhan' => 'Proyektor portable untuk presentasi vendor',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 900000,
                ],
            ],
            'keterangan' => 'Contoh paid belum berjalan [dummy-koperasi-bita]',
        ]), $keuangan->id);
        $paid = $service->confirm($paid, $keuangan->id);
        $service->pay($paid, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_penerimaan_id' => $dompet['bank_bca']->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_vendor_id' => $dompet['kas_operasional']->id,
            'jumlah_diterima' => $paid->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $paid->total_harga_vendor,
            'paid_at' => $awalBulanIni->copy()->addDays(10)->setTime(9, 30),
        ], $keuangan->id);

        $completed = $service->createDraft($this->sewaHardwarePayload($karyawan['siti'], $awalBulanIni->copy()->addDays(35), [
            'details' => [
                [
                    'jenis_hardware' => 'printer',
                    'nama_model_hardware' => 'Fuji Xerox DocuPrint',
                    'spesifikasi_kebutuhan' => 'Multifunction untuk tender',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 1250000,
                ],
                [
                    'jenis_hardware' => 'laptop',
                    'nama_model_hardware' => 'Asus Zenbook',
                    'spesifikasi_kebutuhan' => 'Laptop kerja tim tender',
                    'kuantitas' => 2,
                    'harga_vendor_per_unit' => 1100000,
                ],
            ],
            'keterangan' => 'Contoh selesai multi-hardware [dummy-koperasi-bita]',
        ]), $keuangan->id);
        $completed = $service->confirm($completed, $keuangan->id);
        $completed = $service->pay($completed, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $dompet['kas_operasional']->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_vendor_id' => $dompet['bank_bca']->id,
            'jumlah_diterima' => $completed->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $completed->total_harga_vendor,
            'paid_at' => $awalBulanIni->copy()->addDays(9)->setTime(10, 0),
        ], $keuangan->id);
        $completed = $service->start($completed, $keuangan->id);
        $service->complete($completed, $keuangan->id);

        $running = $service->createDraft($this->sewaHardwarePayload($karyawan['budi'], $awalBulanIni->copy()->addDays(45), [
            'details' => [
                [
                    'jenis_hardware' => 'kamera',
                    'nama_model_hardware' => 'Canon EOS M50',
                    'spesifikasi_kebutuhan' => 'Kamera dokumentasi QC',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 650000,
                ],
            ],
            'keterangan' => 'Contoh kontrak berjalan [dummy-koperasi-bita]',
        ]), $keuangan->id);
        $running = $service->confirm($running, $keuangan->id);
        $running = $service->pay($running, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_penerimaan_id' => $dompet['bank_bca']->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_vendor_id' => $dompet['bank_bca']->id,
            'jumlah_diterima' => $running->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $running->total_harga_vendor,
            'paid_at' => $awalBulanIni->copy()->addDays(11)->setTime(14, 0),
        ], $keuangan->id);
        $service->start($running, $keuangan->id);

        $cancelled = $service->createDraft($this->sewaHardwarePayload($karyawan['andi'], $awalBulanIni->copy()->addDays(48), [
            'details' => [
                [
                    'jenis_hardware' => 'printer',
                    'nama_model_hardware' => 'Vendor Thermal Receipt',
                    'spesifikasi_kebutuhan' => 'Uji coba printer receipt kantor',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 800000,
                ],
            ],
            'keterangan' => 'Contoh batal sebelum paid [dummy-koperasi-bita]',
        ]), $keuangan->id);
        $cancelled = $service->confirm($cancelled, $keuangan->id);
        $service->cancelByFinance($cancelled, 'Kontrak dibatalkan sebelum paid [dummy].', $keuangan->id);

        $refunded = $service->createDraft($this->sewaHardwarePayload($karyawan['lilis'], $awalBulanIni->copy()->addDays(49), [
            'details' => [
                [
                    'jenis_hardware' => 'laptop',
                    'nama_model_hardware' => 'Dell Latitude 5420',
                    'spesifikasi_kebutuhan' => 'Laptop training proyek yang dibatalkan',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 700000,
                ],
            ],
            'keterangan' => 'Contoh refund penuh sebelum berjalan [dummy-koperasi-bita]',
        ]), $keuangan->id);
        $refunded = $service->confirm($refunded, $keuangan->id);
        $refunded = $service->pay($refunded, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_penerimaan_id' => $dompet['bank_bca']->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_vendor_id' => $dompet['kas_operasional']->id,
            'jumlah_diterima' => $refunded->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $refunded->total_harga_vendor,
            'paid_at' => $awalBulanIni->copy()->addDays(12)->setTime(9, 0),
        ], $keuangan->id);
        $service->refundByFinance($refunded, 'Kontrak training dibatalkan sebelum perangkat digunakan [dummy].', $keuangan->id);
    }

    private function sewaHardwarePayload(Karyawan $pic, Carbon $tanggal, array $overrides = []): array
    {
        return array_merge([
            'karyawan_id' => $pic->id,
            'mulai_tanggal' => $tanggal->toDateString(),
            'selesai_tanggal' => $tanggal->copy()->addDays(2)->toDateString(),
            'kebutuhan' => 'Kebutuhan hardware vendor untuk pekerjaan proyek',
            'vendor_nama' => 'Vendor Hardware Nusantara',
            'vendor_kontak' => '0812-0000-8899',
            'vendor_alamat' => 'Jl. Vendor Dummy No. 15, Jakarta',
            'details' => [
                [
                    'jenis_hardware' => 'printer',
                    'nama_model_hardware' => 'Epson EcoTank L3210',
                    'spesifikasi_kebutuhan' => 'Printer warna A4',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 1000000,
                ],
            ],
            'keterangan' => 'Data dummy sewa hardware [dummy-koperasi-bita]',
        ], $overrides);
    }

    private function seedBebanOperasional(
        BebanOperasionalService $service,
        array $dompet,
        User $keuangan,
        Carbon $awalBulanIni
    ): void {
        if (! Schema::hasTable('beban_operasional') || BebanOperasional::query()->exists()) {
            return;
        }

        $akun = [
            'umum' => $this->akunId('beban_operasional'),
            'perawatan' => $this->akunId('beban_perawatan_aset'),
            'atk' => $this->akunId('beban_atk_kantor'),
            'transport' => $this->akunId('beban_transportasi_operasional'),
        ];

        $draftDate = $awalBulanIni->copy()->addDays(13);
        $draft = $service->createDraft([
            'tanggal_beban' => $draftDate->toDateString(),
            'akun_id' => $akun['atk'],
            'dompet_id' => $dompet['kas_operasional']->id,
            'nominal' => 125000,
            'nomor_referensi' => 'BOP-DRAFT-ATK',
            'keterangan' => 'Draft pembelian ATK dan administrasi kantor [dummy-koperasi-bita]',
            'idempotency_key' => 'dummy-beban-draft-atk',
        ], $keuangan->id);
        $this->touchBebanArtifacts($draft, $draftDate);

        $kasDate = $awalBulanIni->copy()->addDays(14);
        $postedKas = $service->createDraft([
            'tanggal_beban' => $kasDate->toDateString(),
            'akun_id' => $akun['umum'],
            'dompet_id' => $dompet['kas_operasional']->id,
            'nominal' => 325000,
            'nomor_referensi' => 'BOP-LISTRIK-AIR',
            'keterangan' => 'Pembayaran listrik dan air kantor koperasi [dummy-koperasi-bita]',
            'idempotency_key' => 'dummy-beban-posted-listrik-air',
        ], $keuangan->id);
        $postedKas = $service->post($postedKas, null, $keuangan->id);
        $this->touchBebanArtifacts($postedKas, $kasDate);

        $bankDate = $awalBulanIni->copy()->addDays(15);
        $postedBank = $service->createDraft([
            'tanggal_beban' => $bankDate->toDateString(),
            'akun_id' => $akun['transport'],
            'dompet_id' => $dompet['bank_bca']->id,
            'nominal' => 275000,
            'nomor_referensi' => 'BOP-TRANSPORT',
            'keterangan' => 'Transportasi kegiatan operasional koperasi [dummy-koperasi-bita]',
            'idempotency_key' => 'dummy-beban-posted-transport',
        ], $keuangan->id);
        $postedBank = $service->post($postedBank, null, $keuangan->id);
        $this->touchBebanArtifacts($postedBank, $bankDate);

        $rapatDate = $awalBulanIni->copy()->addDays(16);
        $postedRapat = $service->createDraft([
            'tanggal_beban' => $rapatDate->toDateString(),
            'akun_id' => $akun['umum'],
            'dompet_id' => $dompet['kas_operasional']->id,
            'nominal' => 180000,
            'nomor_referensi' => 'BOP-RAPAT',
            'keterangan' => 'Biaya rapat pengurus dan konsumsi operasional [dummy-koperasi-bita]',
            'idempotency_key' => 'dummy-beban-posted-rapat',
        ], $keuangan->id);
        $postedRapat = $service->post($postedRapat, null, $keuangan->id);
        $this->touchBebanArtifacts($postedRapat, $rapatDate);

        $reversalDate = $awalBulanIni->copy()->addDays(17);
        $reversed = $service->createDraft([
            'tanggal_beban' => $reversalDate->toDateString(),
            'akun_id' => $akun['umum'],
            'dompet_id' => $dompet['kas_operasional']->id,
            'nominal' => 150000,
            'nomor_referensi' => 'BOP-LANGGANAN-REV',
            'keterangan' => 'Langganan operasional yang direversal penuh karena duplikasi [dummy-koperasi-bita]',
            'idempotency_key' => 'dummy-beban-reversed',
        ], $keuangan->id);
        $reversed = $service->post($reversed, null, $keuangan->id);
        $reversed = $service->reverse($reversed, 'Reversal penuh contoh dummy karena input duplikat.', $keuangan->id);
        $this->touchBebanArtifacts($reversed, $reversalDate);
    }

    private function touchBebanArtifacts(BebanOperasional $beban, Carbon $tanggal): void
    {
        $fresh = $beban->fresh(['details', 'mutasiKas', 'jurnal.details', 'reversal']);

        if (! $fresh) {
            return;
        }

        $this->setTimestamp('beban_operasional', $fresh->id, $tanggal);
        $fresh->details->each(fn ($detail) => $this->setTimestamp('beban_operasional_detail', $detail->id, $tanggal));
        $fresh->mutasiKas->each(fn (MutasiKas $mutasi) => $this->setTimestamp('mutasi_kas', $mutasi->id, $tanggal));
        $fresh->jurnal->each(function ($jurnal) use ($tanggal): void {
            $this->setTimestamp('jurnal_umum', $jurnal->id, $tanggal);
            $jurnal->details->each(fn ($detail) => $this->setTimestamp('jurnal_umum_detail', $detail->id, $tanggal));
        });

        if ($fresh->reversal) {
            DB::table('reversal_transaksi')
                ->where('id', $fresh->reversal->id)
                ->update([
                    'processed_at' => $tanggal->toDateTimeString(),
                    'created_at' => $tanggal->toDateTimeString(),
                    'updated_at' => $tanggal->toDateTimeString(),
                ]);

            MutasiKas::query()
                ->where('referensi_tipe', \App\Models\ReversalTransaksi::class)
                ->where('referensi_id', $fresh->reversal->id)
                ->get()
                ->each(fn (MutasiKas $mutasi) => $this->setTimestamp('mutasi_kas', $mutasi->id, $tanggal));

            DB::table('jurnal_umum')
                ->where('referensi_tipe', \App\Models\ReversalTransaksi::class)
                ->where('referensi_id', $fresh->reversal->id)
                ->get()
                ->each(function ($jurnal) use ($tanggal): void {
                    $this->setTimestamp('jurnal_umum', $jurnal->id, $tanggal);
                    DB::table('jurnal_umum_detail')
                        ->where('jurnal_umum_id', $jurnal->id)
                        ->pluck('id')
                        ->each(fn ($id) => $this->setTimestamp('jurnal_umum_detail', (int) $id, $tanggal));
                });
        }
    }

    private function seedMutasiManual(MutasiKasService $mutasiKasService, array $rows): void
    {
        foreach ($rows as $row) {
            $existing = MutasiKas::query()
                ->where('dompet_id', $row['dompet_id'])
                ->where('tipe', $row['tipe'])
                ->where('jumlah', $row['jumlah'])
                ->where('tanggal', $row['tanggal']->toDateString())
                ->where('keterangan', $row['keterangan'])
                ->whereNull('referensi_tipe')
                ->whereNull('referensi_id')
                ->first();

            if ($existing) {
                continue;
            }

            $mutasi = $mutasiKasService->record([
                'dompet_id' => $row['dompet_id'],
                'tipe' => $row['tipe'],
                'jumlah' => $row['jumlah'],
                'tanggal' => $row['tanggal']->toDateString(),
                'keterangan' => $row['keterangan'],
            ]);

            $this->setTimestamp('mutasi_kas', $mutasi->id, $row['tanggal']);
        }
    }

    private function seedSimpanan(
        SimpananManasukaService $simpananManasukaService,
        array $karyawan,
        array $jenisSimpanan,
        array $dompet,
        User $keuangan,
        array $rows
    ): void {
        foreach ($rows as $row) {
            $anggotaModel = $karyawan[$row['anggota']]->anggota()->first();
            $jenis = $jenisSimpanan[$row['jenis']];
            $idempotencyKey = 'dummy-simpanan:' . $row['anggota'] . ':' . $row['jenis'] . ':' . ($row['jenis_transaksi'] ?? 'setoran') . ':' . $row['dompet'] . ':' . $row['tanggal']->format('Ymd');

            $simpanan = $simpananManasukaService->create([
                'idempotency_key' => $idempotencyKey,
                'anggota_id' => $anggotaModel?->id,
                'jenis_simpanan_id' => $jenis->id,
                'dompet_id' => $dompet[$row['dompet']]->id,
                'jenis_transaksi' => $row['jenis_transaksi'] ?? Simpanan::JENIS_SETORAN,
                'metode_pembayaran' => $row['metode_pembayaran'] ?? Simpanan::METODE_TUNAI,
                'jumlah' => $row['jumlah'],
                'tanggal' => $row['tanggal']->toDateString(),
                'keterangan' => $row['keterangan'],
            ], $keuangan->id);

            $this->setTimestamp('simpanan', $simpanan->id, $row['tanggal']);
            if ($simpanan->mutasiKas) {
                $this->setTimestamp('mutasi_kas', $simpanan->mutasiKas->id, $row['tanggal']);
            }
            if ($simpanan->jurnal) {
                $this->setTimestamp('jurnal_umum', $simpanan->jurnal->id, $row['tanggal']);
                $simpanan->jurnal->details()->get()->each(function ($detail) use ($row): void {
                    $this->setTimestamp('jurnal_umum_detail', $detail->id, $row['tanggal']);
                });
            }

            if (($row['koreksi'] ?? false) && $simpanan->status !== Simpanan::STATUS_REVERSED) {
                $reversal = $simpananManasukaService->koreksi(
                    $simpanan,
                    $row['alasan_koreksi'] ?? 'Koreksi dummy Simpanan Manasuka.',
                    $keuangan->id
                );

                $this->setTimestamp('reversal_transaksi', $reversal->id, $row['tanggal']);
                $reversal->loadMissing(['originalMutasi', 'originalJurnal']);
                MutasiKas::query()
                    ->where('referensi_tipe', \App\Models\ReversalTransaksi::class)
                    ->where('referensi_id', $reversal->id)
                    ->get()
                    ->each(fn (MutasiKas $mutasi) => $this->setTimestamp('mutasi_kas', $mutasi->id, $row['tanggal']));
                \App\Models\JurnalUmum::query()
                    ->where('referensi_tipe', \App\Models\ReversalTransaksi::class)
                    ->where('referensi_id', $reversal->id)
                    ->with('details')
                    ->get()
                    ->each(function ($jurnal) use ($row): void {
                        $this->setTimestamp('jurnal_umum', $jurnal->id, $row['tanggal']);
                        $jurnal->details->each(fn ($detail) => $this->setTimestamp('jurnal_umum_detail', $detail->id, $row['tanggal']));
                    });
            }
        }
    }

    private function seedPinjaman(
        PinjamanKoperasiService $pinjamanService,
        array $karyawan,
        array $dompet,
        User $keuangan,
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $key => $row) {
            $anggota = $karyawan[$row['anggota']]->anggota()->firstOrFail();

            $pinjaman = Pinjaman::query()
                ->where('anggota_id', $anggota->id)
                ->where('status', Pinjaman::STATUS_AKTIF)
                ->first();

            if (! $pinjaman) {
                $pinjaman = $pinjamanService->create([
                    'anggota_id' => $anggota->id,
                    'dompet_id' => $dompet[$row['dompet']]->id,
                    'jumlah_pinjaman' => $row['jumlah_pinjaman'],
                    'tenor_bulan' => $row['tenor_bulan'],
                    'tanggal_pinjaman' => $row['tanggal_pinjaman'],
                    'keterangan' => $row['keterangan'],
                ], $keuangan->id);

                $this->setTimestamp('pinjaman', $pinjaman->id, $row['tanggal_pinjaman']);

                $pinjaman->jadwalCicilan()->get()->each(function ($jadwal) use ($row): void {
                    $this->setTimestamp('jadwal_cicilan_pinjaman', $jadwal->id, $row['tanggal_pinjaman']);
                });

                if ($pinjaman->mutasiKas) {
                    $this->setTimestamp('mutasi_kas', $pinjaman->mutasiKas->id, $row['tanggal_pinjaman']);
                }

                if ($pinjaman->jurnal) {
                    $this->setTimestamp('jurnal_umum', $pinjaman->jurnal->id, $row['tanggal_pinjaman']);
                    $pinjaman->jurnal->details()->get()->each(function ($detail) use ($row): void {
                        $this->setTimestamp('jurnal_umum_detail', $detail->id, $row['tanggal_pinjaman']);
                    });
                }
            }

            $result[$key] = $pinjaman->fresh();
        }

        return $result;
    }

    private function seedPinjamanLifecycleSp5(
        PinjamanKoperasiService $pinjamanService,
        array $karyawan,
        array $dompet,
        User $keuangan,
        Carbon $awalBulanIni
    ): void {
        $rows = [
            'wawan_sp5_draft' => [
                'jumlah_pinjaman' => 750000,
                'tenor_bulan' => 5,
                'tanggal_pengajuan' => $awalBulanIni->copy()->addDays(2),
                'keterangan' => 'Draft pengajuan Pinjaman SP-5 [dummy-koperasi-bita]',
                'action' => 'draft',
            ],
            'yuni_sp5_diajukan' => [
                'jumlah_pinjaman' => 1200000,
                'tenor_bulan' => 6,
                'tanggal_pengajuan' => $awalBulanIni->copy()->addDays(3),
                'keterangan' => 'Pengajuan Pinjaman SP-5 berstatus diajukan [dummy-koperasi-bita]',
                'action' => 'submitted',
            ],
            'farhan_sp5_disetujui' => [
                'jumlah_pinjaman' => 1500000,
                'tenor_bulan' => 8,
                'tanggal_pengajuan' => $awalBulanIni->copy()->addDays(4),
                'keterangan' => 'Pengajuan Pinjaman SP-5 sudah disetujui dan menunggu pencairan [dummy-koperasi-bita]',
                'action' => 'approved',
            ],
            'lina_sp5_ditolak' => [
                'jumlah_pinjaman' => 900000,
                'tenor_bulan' => 4,
                'tanggal_pengajuan' => $awalBulanIni->copy()->addDays(5),
                'keterangan' => 'Pengajuan Pinjaman SP-5 contoh ditolak [dummy-koperasi-bita]',
                'action' => 'rejected',
            ],
            'toni_sp5_dibatalkan' => [
                'jumlah_pinjaman' => 850000,
                'tenor_bulan' => 5,
                'tanggal_pengajuan' => $awalBulanIni->copy()->addDays(6),
                'keterangan' => 'Pengajuan Pinjaman SP-5 contoh dibatalkan [dummy-koperasi-bita]',
                'action' => 'cancelled',
            ],
            'vera_sp5_aktif' => [
                'jumlah_pinjaman' => 2000000,
                'tenor_bulan' => 10,
                'tanggal_pengajuan' => $awalBulanIni->copy()->addDays(7),
                'tanggal_pencairan' => $awalBulanIni->copy()->addDays(8),
                'keterangan' => 'Pengajuan Pinjaman SP-5 sudah dicairkan [dummy-koperasi-bita]',
                'action' => 'active',
            ],
        ];

        foreach ($rows as $anggotaKey => $row) {
            $anggota = $karyawan[$anggotaKey]->anggota()->firstOrFail();

            if (Pinjaman::query()->where('anggota_id', $anggota->id)->exists()) {
                continue;
            }

            $pinjaman = $pinjamanService->createDraft([
                'anggota_id' => $anggota->id,
                'jumlah_pinjaman' => $row['jumlah_pinjaman'],
                'tenor_bulan' => $row['tenor_bulan'],
                'tanggal_pengajuan' => $row['tanggal_pengajuan'],
                'keterangan' => $row['keterangan'],
            ], $keuangan->id);

            $this->setTimestamp('pinjaman', $pinjaman->id, $row['tanggal_pengajuan']);

            if ($row['action'] === 'submitted') {
                $pinjamanService->submit($pinjaman, $keuangan->id);
            }

            if (in_array($row['action'], ['approved', 'active'], true)) {
                $pinjaman = $pinjamanService->submit($pinjaman, $keuangan->id);
                $pinjaman = $pinjamanService->approve($pinjaman, $keuangan->id);
            }

            if ($row['action'] === 'rejected') {
                $pinjaman = $pinjamanService->submit($pinjaman, $keuangan->id);
                $pinjamanService->reject($pinjaman, 'Dokumen pendukung dummy tidak lengkap untuk contoh penolakan SP-5.', $keuangan->id);
            }

            if ($row['action'] === 'cancelled') {
                $pinjamanService->cancel($pinjaman, 'Pengajuan dummy dibatalkan sebelum proses pencairan SP-5.', $keuangan->id);
            }

            if ($row['action'] === 'active') {
                $pinjaman = $pinjamanService->disburse($pinjaman, [
                    'dompet_id' => $dompet['kas_operasional']->id,
                    'tanggal_pencairan' => $row['tanggal_pencairan'],
                ], $keuangan->id);

                $this->setTimestamp('pinjaman', $pinjaman->id, $row['tanggal_pencairan']);
                $pinjaman->jadwalCicilan()->get()->each(function ($jadwal) use ($row): void {
                    $this->setTimestamp('jadwal_cicilan_pinjaman', $jadwal->id, $row['tanggal_pencairan']);
                });
                if ($pinjaman->mutasiKas) {
                    $this->setTimestamp('mutasi_kas', $pinjaman->mutasiKas->id, $row['tanggal_pencairan']);
                }
                if ($pinjaman->jurnal) {
                    $this->setTimestamp('jurnal_umum', $pinjaman->jurnal->id, $row['tanggal_pencairan']);
                    $pinjaman->jurnal->details()->get()->each(function ($detail) use ($row): void {
                        $this->setTimestamp('jurnal_umum_detail', $detail->id, $row['tanggal_pencairan']);
                    });
                }
            }
        }
    }

    private function seedPotongGaji2C(
        PotongGajiBulananService $service,
        array $karyawan,
        User $keuangan,
        Carbon $awalBulanIni,
        array $pinjaman
    ): void {
        $rina = $karyawan['rina']->anggota()->firstOrFail();
        $budi = $karyawan['budi']->anggota()->firstOrFail();
        $siti = $karyawan['siti']->anggota()->firstOrFail();

        $limitRina = $service->findLimitFor($rina, $awalBulanIni)
            ?: $service->createLimit($rina, $awalBulanIni, 500000, $keuangan->id, 'Limit payroll dummy Rina');

        if ($limitRina->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $limitRina = $service->activateLimit($limitRina, $keuangan->id);
        }

        if ($limitRina->status === \App\Models\LimitPotongGajiAnggota::STATUS_ACTIVE) {
            $limitRina = $service->closeLimit($limitRina, $keuangan->id);
        }

        if ($limitRina->status === \App\Models\LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION) {
            $service->confirmLimit($limitRina, $keuangan->id);
        }

        $limitSiti = $service->findLimitFor($siti, $awalBulanIni)
            ?: $service->createLimit($siti, $awalBulanIni, 600000, $keuangan->id, 'Limit payroll dummy Siti untuk POS');

        if ($limitSiti->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $service->activateLimit($limitSiti, $keuangan->id);
        }

        $periodeBerikutnya = $awalBulanIni->copy()->addMonth();
        $limitBudi = $service->findLimitFor($budi, $periodeBerikutnya)
            ?: $service->createLimit($budi, $periodeBerikutnya, 900000, $keuangan->id, 'Limit payroll dummy Budi bulan depan');

        if ($limitBudi->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $service->activateLimit($limitBudi, $keuangan->id);
        }

        foreach ($pinjaman as $loan) {
            $loan->refresh();
        }
    }

    private function seedCicilan(
        MutasiKasService $mutasiKasService,
        array $pinjaman,
        array $dompet,
        array $rows
    ): void {
        foreach ($rows as $row) {
            $pinjamanModel = $pinjaman[$row['pinjaman']]->fresh();

            $cicilan = CicilanPinjaman::firstOrCreate(
                [
                    'pinjaman_id' => $pinjamanModel->id,
                    'periode' => $row['periode'],
                    'tanggal_bayar' => $row['tanggal_bayar']->toDateString(),
                    'jumlah_cicilan' => $row['jumlah_cicilan'],
                ],
                [
                    'status' => 'sudah_bayar',
                ]
            );

            if ($cicilan->wasRecentlyCreated) {
                $sisaPinjaman = max(0, (float) $pinjamanModel->sisa_pinjaman - (float) $row['jumlah_cicilan']);

                $pinjamanModel->update([
                    'sisa_pinjaman' => $sisaPinjaman,
                    'status' => $sisaPinjaman <= 0 ? 'lunas' : 'aktif',
                ]);

                $this->setTimestamp('cicilan_pinjaman', $cicilan->id, $row['tanggal_bayar']);
            }

            $this->ensureReferenceMutasi(
                $mutasiKasService,
                CicilanPinjaman::class,
                $cicilan->id,
                [
                    'dompet_id' => $dompet[$row['dompet']]->id,
                    'tipe' => 'masuk',
                    'jumlah' => $row['jumlah_cicilan'],
                    'keterangan' => 'Pembayaran cicilan pinjaman',
                    'tanggal' => $row['tanggal_bayar']->toDateString(),
                ],
                $row['tanggal_bayar']
            );
        }
    }

    private function seedPenjualan(
        PosCheckoutService $posCheckoutService,
        array $karyawan,
        array $produk,
        array $dompet,
        User $keuangan,
        array $rows
    ): void {
        foreach ($rows as $row) {
            $existing = Penjualan::query()
                ->where('idempotency_key', $row['idempotency_key'])
                ->first();

            if ($existing) {
                continue;
            }

            $payload = [
                'idempotency_key' => $row['idempotency_key'],
                'tipe_pelanggan' => $row['tipe_pelanggan'],
                'metode_pembayaran' => $row['metode_pembayaran'],
                'tanggal_transaksi' => $row['tanggal'],
                'diskon' => $row['diskon'],
                'items' => collect($row['items'])
                    ->map(fn (array $item): array => [
                        'produk_id' => $produk[$item['produk']]->id,
                        'jumlah' => $item['qty'],
                    ])
                    ->all(),
            ];

            if ($row['tipe_pelanggan'] === 'anggota') {
                $payload['anggota_id'] = $karyawan[$row['anggota']]->anggota()->value('id');
            }

            if ($row['tipe_pelanggan'] === 'karyawan') {
                $payload['karyawan_id'] = $karyawan[$row['karyawan']]->id;
            }

            if ($row['metode_pembayaran'] !== 'potong_gaji') {
                $payload['dompet_id'] = $dompet[$row['dompet']]->id;
            }

            $penjualan = $posCheckoutService->checkout($payload, $keuangan->id);

            $this->setTimestamp('penjualan', $penjualan->id, $row['tanggal']);
            if ($penjualan->pembayaran) {
                $this->setTimestamp('pembayaran', $penjualan->pembayaran->id, $row['tanggal']);
            }

            $penjualan->details()->get()->each(function (DetailPenjualan $detail) use ($row): void {
                $this->setTimestamp('detail_penjualan', $detail->id, $row['tanggal']);
            });

            $penjualan->mutasiKas()->get()->each(function (MutasiKas $mutasi) use ($row): void {
                $this->setTimestamp('mutasi_kas', $mutasi->id, $row['tanggal']);
            });

            $penjualan->jurnal()->with('details')->get()->each(function ($jurnal) use ($row): void {
                $this->setTimestamp('jurnal_umum', $jurnal->id, $row['tanggal']);
                $jurnal->details()->get()->each(function ($detail) use ($row): void {
                    $this->setTimestamp('jurnal_umum_detail', $detail->id, $row['tanggal']);
                });
            });
        }
    }

    private function seedStage2EExamples(
        PotongGajiBulananService $potongGajiService,
        PosCheckoutService $posCheckoutService,
        TransaksiReversalService $reversalService,
        array $karyawan,
        array $produk,
        array $dompet,
        User $keuangan,
        Carbon $awalBulanIni
    ): void {
        $siti = $karyawan['siti']->anggota()->firstOrFail();
        $lilis = $karyawan['lilis']->anggota()->firstOrFail();
        $agus = $karyawan['agus']->anggota()->firstOrFail();

        $limitSiti = $potongGajiService->findLimitFor($siti, $awalBulanIni);
        if ($limitSiti && $limitSiti->status === \App\Models\LimitPotongGajiAnggota::STATUS_ACTIVE) {
            $limitSiti = $potongGajiService->closeLimit($limitSiti, $keuangan->id);
        }
        if ($limitSiti && $limitSiti->status === \App\Models\LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION) {
            $potongGajiService->confirmLimit($limitSiti, $keuangan->id);
        }

        $sitiPos = Penjualan::query()->where('idempotency_key', 'dummy-pos-siti-payroll-1')->first();
        if ($sitiPos && ! $sitiPos->reversal_transaksi_id) {
            $reversalService->refundPos($sitiPos, 'Retur penuh dummy confirmed menjadi kredit payroll berikutnya.', $keuangan->id);
        }

        $limitSitiNext = $potongGajiService->findLimitFor($siti, $awalBulanIni->copy()->addMonth())
            ?: $potongGajiService->createLimit($siti, $awalBulanIni->copy()->addMonth(), 300000, $keuangan->id, 'Limit dummy Siti untuk pemakaian kredit refund');
        if ($limitSitiNext->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $limitSitiNext = $potongGajiService->activateLimit($limitSitiNext, $keuangan->id);
        }

        if (! Penjualan::query()->where('idempotency_key', 'dummy-pos-siti-credit-carry-forward')->exists()) {
            $posCheckoutService->checkout([
                'idempotency_key' => 'dummy-pos-siti-credit-carry-forward',
                'tipe_pelanggan' => 'anggota',
                'anggota_id' => $siti->id,
                'metode_pembayaran' => 'potong_gaji',
                'tanggal_transaksi' => $awalBulanIni->copy()->addMonth()->addDays(4),
                'diskon' => 0,
                'items' => [
                    ['produk_id' => $produk['air_mineral']->id, 'jumlah' => 2],
                ],
            ], $keuangan->id);
        }

        if ($limitSitiNext->status === \App\Models\LimitPotongGajiAnggota::STATUS_ACTIVE) {
            $limitSitiNext = $potongGajiService->closeLimit($limitSitiNext, $keuangan->id);
        }
        if ($limitSitiNext->status === \App\Models\LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION) {
            $potongGajiService->confirmLimit($limitSitiNext, $keuangan->id);
        }

        $limitLilis = $potongGajiService->findLimitFor($lilis, $awalBulanIni)
            ?: $potongGajiService->createLimit($lilis, $awalBulanIni, 700000, $keuangan->id, 'Limit dummy Lilis untuk contoh POS pending cancel');
        if ($limitLilis->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $limitLilis = $potongGajiService->activateLimit($limitLilis, $keuangan->id);
        }

        $lilisPos = Penjualan::query()->where('idempotency_key', 'dummy-pos-lilis-payroll-cancel')->first();
        if (! $lilisPos) {
            $lilisPos = $posCheckoutService->checkout([
                'idempotency_key' => 'dummy-pos-lilis-payroll-cancel',
                'tipe_pelanggan' => 'anggota',
                'anggota_id' => $lilis->id,
                'metode_pembayaran' => 'potong_gaji',
                'tanggal_transaksi' => $awalBulanIni->copy()->addDays(8),
                'diskon' => 0,
                'items' => [
                    ['produk_id' => $produk['air_mineral']->id, 'jumlah' => 1],
                ],
            ], $keuangan->id);
        }
        if (! $lilisPos->reversal_transaksi_id) {
            $reversalService->cancelPendingPayrollPos($lilisPos, 'Pembatalan penuh dummy POS payroll sebelum confirmed.', $keuangan->id);
        }

        $cashPos = Penjualan::query()->where('idempotency_key', 'dummy-pos-umum-kas-2')->first();
        if ($cashPos && ! $cashPos->reversal_transaksi_id) {
            $reversalService->refundPos($cashPos, 'Refund penuh dummy transaksi POS tunai.', $keuangan->id);
        }

        $rinaPaidCicilan = CicilanPinjaman::query()
            ->whereHas('pinjaman.anggota.karyawan', fn ($query) => $query->where('email', 'rina.marlina@bita.test'))
            ->where('status', CicilanPinjaman::STATUS_SUDAH_BAYAR)
            ->orderBy('id')
            ->first();
        if ($rinaPaidCicilan && ! $rinaPaidCicilan->reversal_transaksi_id) {
            $reversalService->reverseCicilan($rinaPaidCicilan, 'Reversal penuh dummy cicilan payroll yang salah.', $keuangan->id);
        }

        $agusOutstanding = Simpanan::query()
            ->where('anggota_id', $agus->id)
            ->where('status', Simpanan::STATUS_OUTSTANDING_CASH)
            ->first();
        if ($agusOutstanding) {
            $reversalService->payOutstandingSource(Simpanan::class, $agusOutstanding->id, $dompet['kas_operasional'], $keuangan->id);
        }
    }

    private function confirmDummyPayrollLimit(
        PotongGajiBulananService $service,
        Anggota $anggota,
        Carbon $periode,
        int $nominal,
        User $keuangan,
        string $alasan
    ): void {
        $limit = $service->findLimitFor($anggota, $periode);

        if (! $limit) {
            $limit = $service->createLimit($anggota, $periode, $nominal, $keuangan->id, $alasan);
        }

        if ($limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $limit = $service->activateLimit($limit, $keuangan->id);
        }

        if ($limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_ACTIVE) {
            $limit = $service->closeLimit($limit, $keuangan->id);
        }

        if ($limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION) {
            $service->confirmLimit($limit, $keuangan->id);
        }
    }

    private function seedStage3FExamples(
        KeanggotaanLifecycleService $service,
        MasterDataKoperasiService $masterDataService,
        PotongGajiBulananService $potongGajiService,
        array $karyawan,
        array $dompet,
        User $keuangan,
        Carbon $awalBulanIni
    ): void {
        $agus = $karyawan['agus']->anggota()->with('siklusKeanggotaan.penyelesaian')->first();

        if ($agus) {
            $penyelesaian = $agus->penyelesaianKeanggotaan()
                ->whereNotIn('status', [
                    \App\Models\PenyelesaianKeanggotaan::STATUS_CANCELLED,
                    \App\Models\PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED,
                ])
                ->latest('id')
                ->first();

            if ($penyelesaian) {
                $penyelesaian = $service->refreshSnapshot($penyelesaian);

                if ((float) $penyelesaian->total_offset <= 0 && (float) $penyelesaian->total_hak_anggota > 0) {
                    $service->processOffset($penyelesaian, $keuangan->id);
                }
            }
        }

        $lilis = $karyawan['lilis']->anggota()->with('siklusKeanggotaan.penyelesaian')->first();
        if ($lilis && $lilis->status === Anggota::STATUS_AKTIF) {
            $masterDataService->updateKaryawan($karyawan['lilis']->fresh(), [
                'nama' => $karyawan['lilis']->nama,
                'email' => $karyawan['lilis']->email,
                'telepon' => $karyawan['lilis']->telepon,
                'jabatan' => $karyawan['lilis']->jabatan,
                'status_kerja' => Karyawan::STATUS_BERHENTI,
                'tanggal_berhenti' => $awalBulanIni->copy()->subDays(3)->toDateString(),
            ]);

            $penyelesaianLilis = $karyawan['lilis']->fresh()->anggota?->penyelesaianKeanggotaan()
                ->whereNotIn('status', [
                    \App\Models\PenyelesaianKeanggotaan::STATUS_CANCELLED,
                    \App\Models\PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED,
                ])
                ->latest('id')
                ->first();

            if ($penyelesaianLilis) {
                $service->cancelDeactivation(
                    $penyelesaianLilis,
                    'Contoh dummy SP-4H: penonaktifan Lilis salah input dan dibatalkan.',
                    $keuangan->id
                );
            }
        }

        $nina = $karyawan['nina']->anggota()->with('siklusKeanggotaan.penyelesaian')->first();
        if (! $nina) {
            return;
        }

        if ($nina->status === Anggota::STATUS_AKTIF) {
            $this->confirmDummyPayrollLimit(
                $potongGajiService,
                $nina,
                Carbon::parse('2026-01-01'),
                250000,
                $keuangan,
                'Limit dummy untuk Pokok dan Wajib paid sebelum Nina keluar.'
            );

            $masterDataService->updateKaryawan($karyawan['nina']->fresh(), [
                'nama' => $karyawan['nina']->nama,
                'email' => $karyawan['nina']->email,
                'telepon' => $karyawan['nina']->telepon,
                'jabatan' => $karyawan['nina']->jabatan,
                'status_kerja' => Karyawan::STATUS_BERHENTI,
                'tanggal_berhenti' => $awalBulanIni->copy()->subDays(5)->toDateString(),
            ]);
        }

        $nina = $karyawan['nina']->fresh()->anggota()->first();
        $penyelesaianNina = $nina?->penyelesaianKeanggotaan()
            ->whereNotIn('status', [
                \App\Models\PenyelesaianKeanggotaan::STATUS_CANCELLED,
                \App\Models\PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED,
            ])
            ->latest('id')
            ->first();

        if (! $penyelesaianNina) {
            return;
        }

        $penyelesaianNina = $service->refreshSnapshot($penyelesaianNina);

        if ((float) $penyelesaianNina->total_offset <= 0) {
            $penyelesaianNina = $service->processOffset($penyelesaianNina, $keuangan->id);
        }

        if ((float) $penyelesaianNina->sisa_kewajiban <= 0 && (float) $penyelesaianNina->total_refund > 0 && ! $penyelesaianNina->mutasiKas()->exists()) {
            $penyelesaianNina = $service->processRefund(
                $penyelesaianNina,
                $dompet['kas_operasional'],
                $keuangan->id,
                \App\Models\PenyelesaianKeanggotaan::METODE_TUNAI
            );
        }

        if ($penyelesaianNina->fresh()->status !== \App\Models\PenyelesaianKeanggotaan::STATUS_COMPLETED) {
            $service->complete($penyelesaianNina->fresh(), $keuangan->id);
        }

        if ($karyawan['nina']->fresh()->status_kerja === Karyawan::STATUS_BERHENTI) {
            $masterDataService->updateKaryawan($karyawan['nina']->fresh(), [
                'nama' => $karyawan['nina']->nama,
                'email' => $karyawan['nina']->email,
                'telepon' => $karyawan['nina']->telepon,
                'jabatan' => $karyawan['nina']->jabatan,
                'status_kerja' => Karyawan::STATUS_AKTIF,
                'tanggal_berhenti' => null,
            ]);
        }

        $nina = $karyawan['nina']->fresh()->anggota()->first();
        if ($nina && $nina->status === Anggota::STATUS_NONAKTIF) {
            $service->reRegisterMember(
                $penyelesaianNina->fresh(),
                $awalBulanIni->copy()->toDateString(),
                'Contoh dummy SP-4H: Nina didaftarkan kembali dengan siklus baru.',
                $keuangan->id
            );
        }
    }

    private function ensurePembayaranPenjualan(Penjualan $penjualan, Carbon $tanggal): void
    {
        $pembayaran = Pembayaran::firstOrCreate(
            ['penjualan_id' => $penjualan->id],
            [
                'metode_pembayaran' => 'tunai',
                'jumlah_bayar' => $penjualan->grand_total,
            ]
        );

        if ($pembayaran->wasRecentlyCreated) {
            $this->setTimestamp('pembayaran', $pembayaran->id, $tanggal);
        }
    }

    private function ensureHutangReseller(DetailPenjualan $detail, Carbon $tanggal): void
    {
        DB::table('hutang_reseller')->updateOrInsert(
            [
                'reseller_id' => $detail->reseller_id,
                'detail_penjualan_id' => $detail->id,
            ],
            [
                'jumlah' => $detail->subtotal_setor,
                'status' => 'belum_dibayar',
                'tanggal' => $tanggal->toDateString(),
                'created_at' => $tanggal->toDateTimeString(),
                'updated_at' => $tanggal->toDateTimeString(),
            ]
        );
    }

    private function ensureReferenceMutasi(
        MutasiKasService $mutasiKasService,
        string $referensiTipe,
        int $referensiId,
        array $payload,
        Carbon $tanggal
    ): void {
        $existing = MutasiKas::query()
            ->where('referensi_tipe', $referensiTipe)
            ->where('referensi_id', $referensiId)
            ->first();

        if ($existing) {
            return;
        }

        $mutasi = $mutasiKasService->record([
            'dompet_id' => $payload['dompet_id'],
            'tipe' => $payload['tipe'],
            'jumlah' => $payload['jumlah'],
            'keterangan' => $payload['keterangan'],
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'tanggal' => $payload['tanggal'],
        ]);

        $this->setTimestamp('mutasi_kas', $mutasi->id, $tanggal);
    }

    private function setTimestamp(string $table, int $id, Carbon $timestamp): void
    {
        DB::table($table)
            ->where('id', $id)
            ->update([
                'created_at' => $timestamp->toDateTimeString(),
                'updated_at' => $timestamp->toDateTimeString(),
            ]);
    }
}
