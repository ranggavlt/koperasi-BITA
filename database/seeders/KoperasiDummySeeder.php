<?php

namespace Database\Seeders;

use App\Models\CicilanPinjaman;
use App\Models\DetailPenjualan;
use App\Models\DompetKoperasi;
use App\Models\JenisPinjaman;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\KategoriProduk;
use App\Models\MutasiKas;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\PengurusKoperasi;
use App\Models\Produk;
use App\Models\Reseller;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MutasiKasService;
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

            $this->seedUserDummy();

            $karyawan = $this->seedKaryawan();
            $kategori = $this->seedKategoriProduk();
            $reseller = $this->seedReseller();
            $produk = $this->seedProduk($kategori, $reseller);
            $dompet = $this->seedDompetKoperasi();
            $jenisSimpanan = $this->seedJenisSimpanan();
            $this->seedJenisPinjaman();
            $this->seedPengurusKoperasi();

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
                    'anggota' => 'andi',
                    'jenis' => 'pokok',
                    'jumlah' => 100000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(5),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan pokok anggota baru [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'siti',
                    'jenis' => 'pokok',
                    'jumlah' => 100000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(5),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan pokok anggota baru [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'budi',
                    'jenis' => 'pokok',
                    'jumlah' => 100000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(6),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan pokok anggota baru [dummy-koperasi-bita]',
                ],
                [
                    'anggota' => 'rina',
                    'jenis' => 'pokok',
                    'jumlah' => 100000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(6),
                    'dompet' => 'bank_bca',
                    'keterangan' => 'Setoran simpanan pokok anggota baru [dummy-koperasi-bita]',
                ],
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
                    'tanggal' => $awalBulanIni->copy()->addDays(4),
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

            $pinjaman = $this->seedPinjaman($mutasiKasService, $karyawan, $dompet, [
                'budi_reguler' => [
                    'anggota' => 'budi',
                    'jumlah_pinjaman' => 1500000,
                    'bunga_persen' => 1.50,
                    'tenor_bulan' => 10,
                    'tanggal_pinjaman' => $awalBulanIni->copy()->addDays(3),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Pinjaman Reguler untuk biaya sekolah anak [dummy-koperasi-bita]',
                ],
                'rina_darurat' => [
                    'anggota' => 'rina',
                    'jumlah_pinjaman' => 800000,
                    'bunga_persen' => 1.00,
                    'tenor_bulan' => 6,
                    'tanggal_pinjaman' => $awalBulanLalu->copy()->addDays(12),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Pinjaman Darurat untuk servis motor kerja [dummy-koperasi-bita]',
                ],
                'agus_multiguna' => [
                    'anggota' => 'agus',
                    'jumlah_pinjaman' => 500000,
                    'bunga_persen' => 1.75,
                    'tenor_bulan' => 4,
                    'tanggal_pinjaman' => $awalBulanIni->copy()->addDays(5),
                    'dompet' => 'kas_operasional',
                    'keterangan' => 'Pinjaman Multiguna untuk perlengkapan rumah [dummy-koperasi-bita]',
                ],
            ]);

            $this->seedCicilan($mutasiKasService, $pinjaman, $dompet, [
                [
                    'pinjaman' => 'rina_darurat',
                    'jumlah_cicilan' => 150000,
                    'periode' => $awalBulanLalu->copy()->format('Y-m'),
                    'tanggal_bayar' => $awalBulanIni->copy()->subDays(4),
                    'dompet' => 'bank_bca',
                ],
                [
                    'pinjaman' => 'rina_darurat',
                    'jumlah_cicilan' => 150000,
                    'periode' => $awalBulanIni->copy()->format('Y-m'),
                    'tanggal_bayar' => $awalBulanIni->copy()->addDays(8),
                    'dompet' => 'bank_bca',
                ],
                [
                    'pinjaman' => 'budi_reguler',
                    'jumlah_cicilan' => 200000,
                    'periode' => $awalBulanIni->copy()->format('Y-m'),
                    'tanggal_bayar' => $awalBulanIni->copy()->addDays(9),
                    'dompet' => 'kas_operasional',
                ],
                [
                    'pinjaman' => 'agus_multiguna',
                    'jumlah_cicilan' => 125000,
                    'periode' => $awalBulanIni->copy()->format('Y-m'),
                    'tanggal_bayar' => $awalBulanIni->copy()->addDays(10),
                    'dompet' => 'qris',
                ],
            ]);

            $this->seedPenjualan($mutasiKasService, $karyawan, $produk, $dompet, [
                [
                    'kode_transaksi' => 'PJL-9001',
                    'anggota' => 'andi',
                    'diskon' => 2000,
                    'tanggal' => $awalBulanLalu->copy()->addDays(10)->setTime(9, 15),
                    'dompet' => 'kas_operasional',
                    'items' => [
                        ['produk' => 'air_mineral', 'qty' => 12],
                        ['produk' => 'keripik_pisang', 'qty' => 3],
                    ],
                ],
                [
                    'kode_transaksi' => 'PJL-9002',
                    'anggota' => 'dewi',
                    'diskon' => 0,
                    'tanggal' => $awalBulanLalu->copy()->addDays(18)->setTime(12, 30),
                    'dompet' => 'qris',
                    'items' => [
                        ['produk' => 'sabun_cuci', 'qty' => 2],
                        ['produk' => 'sambal_rumahan', 'qty' => 1],
                    ],
                ],
                [
                    'kode_transaksi' => 'PJL-9003',
                    'anggota' => 'siti',
                    'diskon' => 3000,
                    'tanggal' => $awalBulanIni->copy()->addDay()->setTime(10, 5),
                    'dompet' => 'kas_operasional',
                    'items' => [
                        ['produk' => 'buku_tulis', 'qty' => 5],
                        ['produk' => 'air_mineral', 'qty' => 6],
                    ],
                ],
                [
                    'kode_transaksi' => 'PJL-9004',
                    'anggota' => 'budi',
                    'diskon' => 5000,
                    'tanggal' => $awalBulanIni->copy()->addDays(3)->setTime(15, 20),
                    'dompet' => 'qris',
                    'items' => [
                        ['produk' => 'beras_premium', 'qty' => 1],
                        ['produk' => 'minyak_goreng', 'qty' => 2],
                    ],
                ],
                [
                    'kode_transaksi' => 'PJL-9005',
                    'anggota' => 'rina',
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
                    'kode_transaksi' => 'PJL-9006',
                    'anggota' => 'fitri',
                    'diskon' => 2500,
                    'tanggal' => $awalBulanIni->copy()->addDays(7)->setTime(16, 10),
                    'dompet' => 'bank_bca',
                    'items' => [
                        ['produk' => 'kopi_mix', 'qty' => 2],
                        ['produk' => 'biskuit_cokelat', 'qty' => 3],
                        ['produk' => 'keripik_pisang', 'qty' => 2],
                    ],
                ],
                [
                    'kode_transaksi' => 'PJL-9007',
                    'anggota' => 'lilis',
                    'diskon' => 4000,
                    'tanggal' => $awalBulanIni->copy()->addDays(9)->setTime(13, 0),
                    'dompet' => 'qris',
                    'items' => [
                        ['produk' => 'gula_pasir', 'qty' => 2],
                        ['produk' => 'minyak_goreng', 'qty' => 1],
                        ['produk' => 'roti_sisir', 'qty' => 2],
                    ],
                ],
            ]);
        });
    }

    private function seedUserDummy(): void
    {
        User::updateOrCreate(
            ['email' => 'operator.testing@bita.test'],
            [
                'name' => 'Operator Testing BITA',
                'password' => 'bita12345',
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedKaryawan(): array
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
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $result[$key] = Karyawan::updateOrCreate(
                ['email' => $row['email']],
                $row
            );
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
        $rows = [
            'kas_operasional' => 'Kas Operasional',
            'bank_bca' => 'Bank BCA Koperasi',
            'qris' => 'QRIS Koperasi',
        ];

        $result = [];
        foreach ($rows as $key => $namaDompet) {
            $result[$key] = DompetKoperasi::firstOrCreate(
                ['nama_dompet' => $namaDompet],
                ['saldo' => 0]
            );
        }

        return $result;
    }

    private function seedJenisSimpanan(): array
    {
        $rows = [
            'pokok' => [
                'nama_jenis' => 'Simpanan Pokok',
                'wajib' => true,
                'nominal_default' => 100000,
                'keterangan' => 'Setoran awal saat anggota mulai aktif di koperasi.',
            ],
            'wajib' => [
                'nama_jenis' => 'Simpanan Wajib Bulanan',
                'wajib' => true,
                'nominal_default' => 50000,
                'keterangan' => 'Setoran wajib bulanan untuk menjaga likuiditas koperasi.',
            ],
            'sukarela' => [
                'nama_jenis' => 'Simpanan Sukarela',
                'wajib' => false,
                'nominal_default' => null,
                'keterangan' => 'Setoran sukarela anggota di luar kewajiban rutin.',
            ],
            'hari_raya' => [
                'nama_jenis' => 'Simpanan Hari Raya',
                'wajib' => false,
                'nominal_default' => 25000,
                'keterangan' => 'Tabungan kolektif untuk kebutuhan hari raya dan akhir tahun.',
            ],
        ];

        $result = [];
        foreach ($rows as $key => $row) {
            $result[$key] = JenisSimpanan::firstOrCreate(
                ['nama_jenis' => $row['nama_jenis']],
                $row
            );
        }

        return $result;
    }

    private function seedJenisPinjaman(): void
    {
        $rows = [
            [
                'nama_pinjaman' => 'Pinjaman Reguler',
                'bunga_persen' => 1.50,
                'tenor_bulan' => 12,
                'keterangan' => 'Pinjaman umum untuk kebutuhan rumah tangga dan pendidikan.',
            ],
            [
                'nama_pinjaman' => 'Pinjaman Darurat',
                'bunga_persen' => 1.00,
                'tenor_bulan' => 6,
                'keterangan' => 'Pinjaman cepat untuk kebutuhan mendesak anggota.',
            ],
            [
                'nama_pinjaman' => 'Pinjaman Pendidikan',
                'bunga_persen' => 1.25,
                'tenor_bulan' => 10,
                'keterangan' => 'Pinjaman terjadwal untuk uang sekolah dan kursus.',
            ],
            [
                'nama_pinjaman' => 'Pinjaman Multiguna',
                'bunga_persen' => 1.75,
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

    private function seedPengurusKoperasi(): void
    {
        if (! Schema::hasTable('pengurus_koperasi')) {
            return;
        }

        $rows = [
            [
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.pengurus@bita.test',
                'telepon' => '081310000301',
                'jabatan' => 'Ketua Pengurus',
            ],
            [
                'nama' => 'Siti Rahmawati',
                'email' => 'siti.pengurus@bita.test',
                'telepon' => '081310000302',
                'jabatan' => 'Sekretaris',
            ],
            [
                'nama' => 'Rudi Hartono',
                'email' => 'rudi.pengurus@bita.test',
                'telepon' => '081310000303',
                'jabatan' => 'Bendahara',
            ],
        ];

        foreach ($rows as $row) {
            PengurusKoperasi::updateOrCreate(
                ['email' => $row['email']],
                $row
            );
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
            $simpanan = Simpanan::firstOrCreate(
                [
                    'karyawan_id' => $karyawan[$row['anggota']]->id,
                    'jenis_simpanan_id' => $jenisSimpanan[$row['jenis']]->id,
                    'jumlah' => $row['jumlah'],
                    'tanggal' => $row['tanggal']->toDateString(),
                    'keterangan' => $row['keterangan'],
                ]
            );

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
        MutasiKasService $mutasiKasService,
        array $karyawan,
        array $dompet,
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $key => $row) {
            $pinjaman = Pinjaman::firstOrCreate(
                [
                    'karyawan_id' => $karyawan[$row['anggota']]->id,
                    'jumlah_pinjaman' => $row['jumlah_pinjaman'],
                    'tanggal_pinjaman' => $row['tanggal_pinjaman']->toDateString(),
                    'keterangan' => $row['keterangan'],
                ],
                [
                    'bunga_persen' => $row['bunga_persen'],
                    'tenor_bulan' => $row['tenor_bulan'],
                    'sisa_pinjaman' => $row['jumlah_pinjaman'],
                    'status' => 'aktif',
                ]
            );

            if ($pinjaman->wasRecentlyCreated) {
                $this->setTimestamp('pinjaman', $pinjaman->id, $row['tanggal_pinjaman']);
            }

            $this->ensureReferenceMutasi(
                $mutasiKasService,
                Pinjaman::class,
                $pinjaman->id,
                [
                    'dompet_id' => $dompet[$row['dompet']]->id,
                    'tipe' => 'keluar',
                    'jumlah' => $row['jumlah_pinjaman'],
                    'keterangan' => 'Pencairan pinjaman anggota',
                    'tanggal' => $row['tanggal_pinjaman']->toDateString(),
                ],
                $row['tanggal_pinjaman']
            );

            $result[$key] = $pinjaman->fresh();
        }

        return $result;
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
        MutasiKasService $mutasiKasService,
        array $karyawan,
        array $produk,
        array $dompet,
        array $rows
    ): void {
        foreach ($rows as $row) {
            $penjualan = Penjualan::where('kode_transaksi', $row['kode_transaksi'])->first();

            if (! $penjualan) {
                $totalHarga = 0;
                foreach ($row['items'] as $item) {
                    $totalHarga += $produk[$item['produk']]->harga_jual * $item['qty'];
                }

                $penjualan = Penjualan::create([
                    'kode_transaksi' => $row['kode_transaksi'],
                    'karyawan_id' => $karyawan[$row['anggota']]->id,
                    'total_harga' => $totalHarga,
                    'diskon' => $row['diskon'],
                    'grand_total' => $totalHarga - $row['diskon'],
                ]);

                $this->setTimestamp('penjualan', $penjualan->id, $row['tanggal']);

                foreach ($row['items'] as $item) {
                    $produkModel = $produk[$item['produk']]->fresh();

                    $detail = DetailPenjualan::create([
                        'penjualan_id' => $penjualan->id,
                        'produk_id' => $produkModel->id,
                        'qty' => $item['qty'],
                        'harga' => $produkModel->harga_jual,
                        'subtotal' => $produkModel->harga_jual * $item['qty'],
                        'konsinyasi' => $produkModel->konsinyasi,
                        'reseller_id' => $produkModel->reseller_id,
                        'harga_setor' => $produkModel->harga_setor,
                        'subtotal_setor' => $produkModel->harga_setor * $item['qty'],
                    ]);

                    $this->setTimestamp('detail_penjualan', $detail->id, $row['tanggal']);

                    $produkModel->decrement('stok', $item['qty']);

                    if ($produkModel->konsinyasi) {
                        $this->ensureHutangReseller($detail, $row['tanggal']);
                    }
                }
            } else {
                foreach ($penjualan->details as $detail) {
                    if ($detail->konsinyasi) {
                        $this->ensureHutangReseller($detail, $row['tanggal']);
                    }
                }
            }

            $this->ensureReferenceMutasi(
                $mutasiKasService,
                Penjualan::class,
                $penjualan->id,
                [
                    'dompet_id' => $dompet[$row['dompet']]->id,
                    'tipe' => 'masuk',
                    'jumlah' => $penjualan->grand_total,
                    'keterangan' => 'Penerimaan dari penjualan ' . $penjualan->kode_transaksi,
                    'tanggal' => $row['tanggal']->toDateString(),
                ],
                $row['tanggal']
            );
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
