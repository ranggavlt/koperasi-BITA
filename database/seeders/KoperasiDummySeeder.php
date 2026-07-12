<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\CicilanPinjaman;
use App\Models\DetailPenjualan;
use App\Models\DompetKoperasi;
use App\Models\JenisPinjaman;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\KategoriProduk;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\PengurusKoperasi;
use App\Models\Produk;
use App\Models\Reseller;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MutasiKasService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use App\Services\TransaksiReversalService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
            $pinjamanService = app(PinjamanKoperasiService::class);
            $posCheckoutService = app(PosCheckoutService::class);
            $potongGajiService = app(PotongGajiBulananService::class);
            $reversalService = app(TransaksiReversalService::class);

            $keuangan = $this->seedUserDummy();

            $karyawan = $this->seedKaryawan($masterDataService);
            $jenisSimpanan = $this->seedJenisSimpanan();
            $anggota = $this->seedAnggota($karyawan, $masterDataService);
            $kategori = $this->seedKategoriProduk();
            $reseller = $this->seedReseller();
            $produk = $this->seedProduk($kategori, $reseller);
            $dompet = $this->seedDompetKoperasi();
            $this->seedJenisPinjaman();
            $this->seedPengurusKoperasi($anggota, $masterDataService);

            $this->seedMutasiManual($mutasiKasService, [
                [
                    'dompet_id' => $dompet['kas_operasional']->id,
                    'tipe' => 'masuk',
                    'jumlah' => 12500000,
                    'tanggal' => $awalBulanLalu->copy()->addDay(),
                    'keterangan' => 'Saldo awal kas operasional koperasi [dummy-koperasi-bita]',
                ],
                [
                    'dompet_id' => $dompet['bank_bca']->id,
                    'tipe' => 'masuk',
                    'jumlah' => 25000000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(2),
                    'keterangan' => 'Saldo awal rekening koperasi [dummy-koperasi-bita]',
                ],
                [
                    'dompet_id' => $dompet['qris']->id,
                    'tipe' => 'masuk',
                    'jumlah' => 2500000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(3),
                    'keterangan' => 'Saldo awal dompet QRIS koperasi [dummy-koperasi-bita]',
                ],
                [
                    'dompet_id' => $dompet['kas_operasional']->id,
                    'tipe' => 'keluar',
                    'jumlah' => 275000,
                    'tanggal' => $awalBulanIni->copy()->addDays(1),
                    'keterangan' => 'Belanja ATK, struk, dan plastik kasir [dummy-koperasi-bita]',
                ],
                [
                    'dompet_id' => $dompet['bank_bca']->id,
                    'tipe' => 'masuk',
                    'jumlah' => 1250000,
                    'tanggal' => $awalBulanIni->copy()->addDays(2),
                    'keterangan' => 'Top up modal kerja dari pengurus [dummy-koperasi-bita]',
                ],
            ]);

            $this->seedSimpanan($mutasiKasService, $karyawan, $jenisSimpanan, $dompet, [
                [
                    'anggota' => 'fitri',
                    'jenis' => 'wajib',
                    'jumlah' => 50000,
                    'tanggal' => $awalBulanIni->copy()->addDays(2),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan wajib bulan berjalan [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'lilis',
                    'jenis' => 'wajib',
                    'jumlah' => 50000,
                    'tanggal' => $awalBulanIni->copy()->addDays(2),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan wajib bulan berjalan [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'agus',
                    'jenis' => 'sukarela',
                    'jumlah' => 150000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(18),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Titip simpanan sukarela untuk cadangan lebaran [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'dewi',
                    'jenis' => 'hari_raya',
                    'jumlah' => 200000,
                    'tanggal' => $awalBulanIni->copy()->addDays(6),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Program simpanan hari raya keluarga [dummy-koperasi-bita]',
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

            $masterDataService->updateKaryawan($karyawan['agus'], [
                'nama' => $karyawan['agus']->nama,
                'email' => $karyawan['agus']->email,
                'telepon' => $karyawan['agus']->telepon,
                'jabatan' => $karyawan['agus']->jabatan,
                'status_kerja' => Karyawan::STATUS_BERHENTI,
                'tanggal_berhenti' => '2026-06-30',
            ]);

            $this->seedPotongGaji2C($potongGajiService, $karyawan, $keuangan, $awalBulanIni, $pinjaman);

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
        });
    }

    private function seedUserDummy(): User
    {
        return User::updateOrCreate(
            ['email' => 'operator.testing@bita.test'],
            [
                'name' => 'Operator Testing BITA',
                'password' => 'bita12345',
                'role' => 'keuangan',
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedKaryawan(MasterDataKoperasiService $service): array
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
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $data = $row + [
                'status_kerja' => Karyawan::STATUS_AKTIF,
                'tanggal_berhenti' => null,
            ];
            $existing = Karyawan::query()->where('email', $row['email'])->first();

            $result[$key] = $existing
                ? $service->updateKaryawan($existing, $data)
                : $service->createKaryawan($data);
        }

        return $result;
    }

    private function seedAnggota(array $karyawan, MasterDataKoperasiService $service): array
    {
        $rows = [
            'andi' => ['tanggal_bergabung' => '2026-01-05', 'alamat' => 'Jl. Dummy Melati No. 1', 'plafon_pinjaman' => 3000000],
            'siti' => ['tanggal_bergabung' => '2026-01-06', 'alamat' => 'Jl. Dummy Melati No. 2', 'plafon_pinjaman' => 5000000],
            'budi' => ['tanggal_bergabung' => '2026-01-07', 'alamat' => 'Jl. Dummy Kenanga No. 3', 'plafon_pinjaman' => 2500000],
            'rina' => ['tanggal_bergabung' => '2026-01-08', 'alamat' => 'Jl. Dummy Kenanga No. 4', 'plafon_pinjaman' => 2000000],
            'agus' => ['tanggal_bergabung' => '2026-01-09', 'alamat' => 'Jl. Dummy Mawar No. 5', 'plafon_pinjaman' => 1500000],
            'dewi' => ['tanggal_bergabung' => '2026-01-10', 'alamat' => 'Jl. Dummy Mawar No. 6', 'plafon_pinjaman' => 5000000],
            'fitri' => ['tanggal_bergabung' => '2026-01-11', 'alamat' => 'Jl. Dummy Anggrek No. 7', 'plafon_pinjaman' => 2500000],
            'lilis' => ['tanggal_bergabung' => '2026-01-12', 'alamat' => 'Jl. Dummy Anggrek No. 8', 'plafon_pinjaman' => 3500000],
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

            $result[$key] = Produk::firstOrCreate($lookup, $row);
        }

        return $result;
    }

    private function seedDompetKoperasi(): array
    {
        $kasAkunId = \App\Models\Akun::query()->where('kode_akun', '101')->value('id');
        $bankAkunId = \App\Models\Akun::query()->where('kode_akun', '102')->value('id');

        $rows = [
            'kas_operasional' => ['nama_dompet' => 'Kas Operasional', 'akun_id' => $kasAkunId, 'jenis_dompet' => 'kas', 'is_default_penerimaan_payroll' => false],
            'bank_bca' => ['nama_dompet' => 'Bank BCA Koperasi', 'akun_id' => $bankAkunId, 'jenis_dompet' => 'bank', 'is_default_penerimaan_payroll' => true],
            'qris' => ['nama_dompet' => 'QRIS Koperasi', 'akun_id' => $bankAkunId, 'jenis_dompet' => 'bank', 'is_default_penerimaan_payroll' => false],
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
                ['saldo' => 0, 'akun_id' => $row['akun_id'], 'jenis_dompet' => $row['jenis_dompet'], 'is_default_penerimaan_payroll' => $row['is_default_penerimaan_payroll']]
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

    private function seedJenisSimpanan(): array
    {
        $akunIds = [
            'pokok' => $this->akunId('simpanan_pokok'),
            'wajib' => $this->akunId('simpanan_wajib'),
            'sukarela' => $this->akunId('simpanan_sukarela'),
            'hari_raya' => $this->akunId('simpanan_khusus'),
        ];

        $rows = [
            'pokok' => [
                'akun_id' => $akunIds['pokok'],
                'kode' => JenisSimpanan::KODE_SIMPANAN_POKOK,
                'nama_jenis' => 'Simpanan Pokok',
                'wajib' => true,
                'aktif' => true,
                'nominal_default' => 100000,
                'keterangan' => 'Setoran awal saat anggota mulai aktif di koperasi.',
            ],
            'wajib' => [
                'akun_id' => $akunIds['wajib'],
                'kode' => 'SIMPANAN_WAJIB_BULANAN',
                'nama_jenis' => 'Simpanan Wajib Bulanan',
                'wajib' => true,
                'aktif' => true,
                'nominal_default' => 50000,
                'keterangan' => 'Setoran wajib bulanan untuk menjaga likuiditas koperasi.',
            ],
            'sukarela' => [
                'akun_id' => $akunIds['sukarela'],
                'kode' => 'SIMPANAN_SUKARELA',
                'nama_jenis' => 'Simpanan Sukarela',
                'wajib' => false,
                'aktif' => true,
                'nominal_default' => null,
                'keterangan' => 'Setoran sukarela anggota di luar kewajiban rutin.',
            ],
            'hari_raya' => [
                'akun_id' => $akunIds['hari_raya'],
                'kode' => 'SIMPANAN_HARI_RAYA',
                'nama_jenis' => 'Simpanan Hari Raya',
                'wajib' => false,
                'aktif' => true,
                'nominal_default' => 25000,
                'keterangan' => 'Tabungan kolektif untuk kebutuhan hari raya dan akhir tahun.',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $existing = JenisSimpanan::query()
                ->where('kode', $row['kode'])
                ->orWhere('nama_jenis', $row['nama_jenis'])
                ->first();

            if ($existing) {
                $existing->update($row);
                $result[$key] = $existing->fresh();
            } else {
                $result[$key] = JenisSimpanan::query()->create($row);
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
        MutasiKasService $mutasiKasService,
        array $karyawan,
        array $jenisSimpanan,
        array $dompet,
        array $rows
    ): void {
        foreach ($rows as $row) {
            $anggotaId = $karyawan[$row['anggota']]->anggota()->value('id');
            $jenis = $jenisSimpanan[$row['jenis']];
            $simpanan = Simpanan::firstOrCreate(
                [
                    'karyawan_id' => $karyawan[$row['anggota']]->id,
                    'jenis_simpanan_id' => $jenis->id,
                    'jumlah' => $row['jumlah'],
                    'tanggal' => $row['tanggal']->toDateString(),
                    'keterangan' => $row['keterangan'],
                ],
                [
                    'idempotency_key' => 'dummy-simpanan:' . $row['anggota'] . ':' . $row['jenis'] . ':' . $row['tanggal']->format('Ymd'),
                    'anggota_id' => $anggotaId,
                    'kode_jenis_snapshot' => $jenis->kode,
                    'nama_jenis_snapshot' => $jenis->nama_jenis,
                    'nominal_snapshot' => $row['jumlah'],
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'status' => Simpanan::STATUS_SETTLED,
                    'settled_at' => $row['tanggal']->toDateTimeString(),
                ]
            );

            $updates = [];
            if ((int) $simpanan->anggota_id !== (int) $anggotaId) {
                $updates['anggota_id'] = $anggotaId;
            }
            if ($simpanan->kode_jenis_snapshot !== $jenis->kode) {
                $updates['kode_jenis_snapshot'] = $jenis->kode;
            }
            if ($simpanan->nama_jenis_snapshot !== $jenis->nama_jenis) {
                $updates['nama_jenis_snapshot'] = $jenis->nama_jenis;
            }
            if ($simpanan->nominal_snapshot === null) {
                $updates['nominal_snapshot'] = $row['jumlah'];
            }
            if ($simpanan->metode_pembayaran === null) {
                $updates['metode_pembayaran'] = Simpanan::METODE_TUNAI;
            }
            if ($simpanan->status === null) {
                $updates['status'] = Simpanan::STATUS_SETTLED;
            }
            if ($simpanan->settled_at === null) {
                $updates['settled_at'] = $row['tanggal']->toDateTimeString();
            }
            if ($simpanan->idempotency_key === null) {
                $updates['idempotency_key'] = 'dummy-simpanan:' . $row['anggota'] . ':' . $row['jenis'] . ':' . $row['tanggal']->format('Ymd');
            }

            if ($updates !== []) {
                $simpanan->update($updates);
            }

            if ($simpanan->wasRecentlyCreated) {
                $this->setTimestamp('simpanan', $simpanan->id, $row['tanggal']);
            }

            $this->ensureReferenceMutasi(
                $mutasiKasService,
                Simpanan::class,
                $simpanan->id,
                [
                    'dompet_id' => $dompet[$row['dompet']]->id,
                    'tipe' => 'masuk',
                    'jumlah' => $row['jumlah'],
                    'keterangan' => 'Penerimaan simpanan anggota',
                    'tanggal' => $row['tanggal']->toDateString(),
                ],
                $row['tanggal']
            );
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
            ?: $service->createLimit($budi, $periodeBerikutnya, 500000, $keuangan->id, 'Limit payroll dummy Budi bulan depan');

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
