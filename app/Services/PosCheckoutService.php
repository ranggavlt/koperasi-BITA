<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\DetailPenjualan;
use App\Models\DompetKoperasi;
use App\Models\HutangReseller;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PosCheckoutService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService,
        private readonly PotongGajiBulananService $potongGajiService
    ) {
    }

    public function checkout(array $data, ?int $userId = null): Penjualan
    {
        try {
            return DB::transaction(function () use ($data, $userId): Penjualan {
                $tanggal = $this->normalizeTanggal($data['tanggal_transaksi'] ?? null);
                $tipe = (string) $data['tipe_pelanggan'];
                $metode = (string) $data['metode_pembayaran'];
                $processed = $this->processItems($data['items']);
                $diskon = $this->rupiahInt($data['diskon'] ?? 0);
                $grandTotal = max(0, $processed['total'] - $diskon);

                if ($grandTotal <= 0) {
                    throw ValidationException::withMessages([
                        'grand_total' => 'Grand total penjualan harus lebih besar dari nol.',
                    ]);
                }

                $context = $this->resolveCustomer($tipe, $data);
                $dompet = null;

                if ($tipe !== Penjualan::TIPE_ANGGOTA && $metode === Pembayaran::METODE_POTONG_GAJI) {
                    throw ValidationException::withMessages([
                        'metode_pembayaran' => 'Potong Gaji hanya boleh digunakan untuk pelanggan Anggota aktif.',
                    ]);
                }

                if ($tipe === Penjualan::TIPE_ANGGOTA && ! in_array($metode, [Pembayaran::METODE_TUNAI, Pembayaran::METODE_POTONG_GAJI], true)) {
                    throw ValidationException::withMessages([
                        'metode_pembayaran' => 'Anggota aktif hanya dapat memilih Tunai atau Potong Gaji pada POS.',
                    ]);
                }

                if ($metode === Pembayaran::METODE_POTONG_GAJI) {
                    $this->assertPayrollCheckoutAllowed($context['anggota'], $tanggal, $grandTotal);
                } else {
                    $dompet = $this->dompetForPayment((int) $data['dompet_id'], $metode);
                }

                $kode = $this->nextKodePenjualan($tanggal);
                $idempotencyKey = (string) ($data['idempotency_key'] ?? ('pos:checkout:' . $kode));

                $penjualan = Penjualan::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'kode_transaksi' => $kode,
                    'tipe_pelanggan' => $tipe,
                    'anggota_id' => $context['anggota']?->id,
                    'karyawan_id' => $context['karyawan']?->id,
                    'tanggal_transaksi' => $tanggal,
                    'total_harga' => $processed['total'],
                    'diskon' => $diskon,
                    'grand_total' => $grandTotal,
                    'created_by' => $userId,
                ]);

                $this->createDetailsAndHutang($penjualan, $processed['items'], $tanggal);

                $pembayaran = Pembayaran::query()->create([
                    'idempotency_key' => 'pos:pembayaran:' . $penjualan->id,
                    'penjualan_id' => $penjualan->id,
                    'metode_pembayaran' => $metode,
                    'status' => $metode === Pembayaran::METODE_POTONG_GAJI
                        ? Pembayaran::STATUS_PENDING_PAYROLL
                        : Pembayaran::STATUS_PAID,
                    'dompet_id' => $dompet?->id,
                    'jumlah_bayar' => $grandTotal . '.00',
                    'paid_at' => $metode === Pembayaran::METODE_POTONG_GAJI ? null : $tanggal,
                    'created_by' => $userId,
                ]);

                if ($metode === Pembayaran::METODE_POTONG_GAJI) {
                    $ledger = $this->consumePayrollLimit($penjualan, $context['anggota'], $tanggal, $grandTotal, $userId);
                    $pembayaran->update(['pemakaian_potong_gaji_id' => $ledger->id]);
                } else {
                    $this->recordNonPayrollCashIn($penjualan, $dompet, $grandTotal, $tanggal);
                }

                $this->akuntansiService->recordPenjualanPos($penjualan, $pembayaran->fresh(), $dompet?->akun, $userId);

                return $penjualan->fresh(['anggota.karyawan', 'karyawan', 'details.produk', 'pembayaran.ledger', 'mutasiKas', 'jurnal.details']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'checkout' => 'Transaksi POS ini sudah pernah diproses. Muat ulang halaman untuk melihat status terakhir.',
            ]);
        }
    }

    /**
     * @return array{karyawan:?Karyawan, anggota:?Anggota}
     */
    private function resolveCustomer(string $tipe, array $data): array
    {
        return match ($tipe) {
            Penjualan::TIPE_ANGGOTA => $this->resolveAnggotaCustomer((int) ($data['anggota_id'] ?? 0)),
            Penjualan::TIPE_KARYAWAN => $this->resolveKaryawanCustomer((int) ($data['karyawan_id'] ?? 0)),
            Penjualan::TIPE_UMUM => ['karyawan' => null, 'anggota' => null],
            default => throw ValidationException::withMessages(['tipe_pelanggan' => 'Tipe pelanggan tidak valid.']),
        };
    }

    private function resolveAnggotaCustomer(int $anggotaId): array
    {
        $anggota = Anggota::query()
            ->with('karyawan')
            ->lockForUpdate()
            ->findOrFail($anggotaId);

        if ($anggota->status !== Anggota::STATUS_AKTIF || $anggota->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'POS Anggota hanya untuk Anggota aktif dengan Karyawan aktif.',
            ]);
        }

        return ['karyawan' => $anggota->karyawan, 'anggota' => $anggota];
    }

    private function resolveKaryawanCustomer(int $karyawanId): array
    {
        $karyawan = Karyawan::query()
            ->with('anggota')
            ->lockForUpdate()
            ->findOrFail($karyawanId);

        if ($karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'POS Karyawan hanya untuk Karyawan aktif.',
            ]);
        }

        if ($karyawan->anggota?->status === Anggota::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Karyawan yang masih Anggota aktif wajib bertransaksi sebagai Anggota.',
            ]);
        }

        return ['karyawan' => $karyawan, 'anggota' => null];
    }

    private function assertPayrollCheckoutAllowed(?Anggota $anggota, CarbonImmutable $tanggal, int $grandTotal): void
    {
        if (! $anggota) {
            throw ValidationException::withMessages([
                'metode_pembayaran' => 'Potong gaji hanya boleh digunakan Anggota aktif.',
            ]);
        }

        $pendingPokok = Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->where('status', Simpanan::STATUS_PENDING_PAYROLL)
            ->exists();

        if ($pendingPokok) {
            throw ValidationException::withMessages([
                'limit' => 'Simpanan Pokok pending harus dialokasikan pada limit sebelum POS potong gaji.',
            ]);
        }

        $limit = $this->potongGajiService->findLimitFor($anggota, $tanggal);

        if (! $limit) {
            throw ValidationException::withMessages([
                'limit' => 'Limit bulan ini belum ditetapkan.',
            ]);
        }

        $limit = \App\Models\LimitPotongGajiAnggota::query()->lockForUpdate()->findOrFail($limit->id);

        if ($limit->status !== \App\Models\LimitPotongGajiAnggota::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'limit' => 'Limit potong gaji bulan ini belum active.',
            ]);
        }

        $availableCents = $this->rupiahInt($limit->limit_nominal) * 100 - $this->potongGajiService->reservedAndConsumedCents($limit);

        if (($grandTotal * 100) > $availableCents) {
            throw ValidationException::withMessages([
                'limit' => 'Sisa limit potong gaji tidak mencukupi.',
            ]);
        }
    }

    private function dompetForPayment(int $dompetId, string $metode): DompetKoperasi
    {
        if ($metode === Pembayaran::METODE_POTONG_GAJI) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Potong gaji tidak memilih Dompet saat checkout.',
            ]);
        }

        if (! in_array($metode, [Pembayaran::METODE_TUNAI, Pembayaran::METODE_TRANSFER_BANK, Pembayaran::METODE_QRIS], true)) {
            throw ValidationException::withMessages([
                'metode_pembayaran' => 'Metode pembayaran POS tidak valid.',
            ]);
        }

        $dompet = DompetKoperasi::query()->with('akun')->lockForUpdate()->findOrFail($dompetId);
        $expectedJenis = $metode === Pembayaran::METODE_TUNAI
            ? DompetKoperasi::JENIS_KAS
            : DompetKoperasi::JENIS_BANK;

        if ($dompet->jenis_dompet !== $expectedJenis) {
            throw ValidationException::withMessages([
                'dompet_id' => $metode === Pembayaran::METODE_TUNAI
                    ? 'Tunai wajib masuk Dompet Kas.'
                    : 'Transfer Bank dan QRIS wajib masuk Dompet Bank.',
            ]);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet wajib memiliki COA Aset aktif dengan saldo normal Debit.',
            ]);
        }

        return $dompet;
    }

    private function processItems(array $rows): array
    {
        $total = 0;
        $processed = [];
        $requested = [];

        foreach ($rows as $index => $row) {
            $produkId = (int) ($row['produk_id'] ?? 0);
            $jumlah = (int) ($row['jumlah'] ?? 0);

            if ($produkId <= 0 || $jumlah <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.jumlah" => 'Produk dan jumlah wajib valid.',
                ]);
            }

            $requested[$produkId] = ($requested[$produkId] ?? 0) + $jumlah;
        }

        $products = Produk::query()
            ->whereIn('id', array_keys($requested))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requested as $produkId => $jumlah) {
            $produk = $products->get($produkId);

            if (! $produk) {
                throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan.']);
            }

            if ($produk->stok < $jumlah) {
                throw ValidationException::withMessages([
                    'items' => "Stok {$produk->nama_produk} kurang. Sisa {$produk->stok}, diminta {$jumlah}.",
                ]);
            }

            $subtotal = (int) $produk->harga_jual * $jumlah;
            $total += $subtotal;
            $processed[] = compact('produk', 'jumlah', 'subtotal');
        }

        return ['items' => $processed, 'total' => $total];
    }

    private function createDetailsAndHutang(Penjualan $penjualan, array $items, CarbonImmutable $tanggal): void
    {
        foreach ($items as $item) {
            /** @var Produk $produk */
            $produk = $item['produk'];
            $jumlah = (int) $item['jumlah'];
            $subtotal = (int) $item['subtotal'];

            $detail = DetailPenjualan::query()->create([
                'penjualan_id' => $penjualan->id,
                'produk_id' => $produk->id,
                'qty' => $jumlah,
                'harga' => $produk->harga_jual,
                'subtotal' => $subtotal,
                'konsinyasi' => $produk->konsinyasi,
                'reseller_id' => $produk->reseller_id,
                'harga_setor' => $produk->harga_setor,
                'subtotal_setor' => (int) $produk->harga_setor * $jumlah,
            ]);

            if ($detail->konsinyasi && $detail->reseller_id) {
                HutangReseller::query()->create([
                    'reseller_id' => $detail->reseller_id,
                    'detail_penjualan_id' => $detail->id,
                    'jumlah' => $detail->subtotal_setor,
                    'status' => 'belum_dibayar',
                    'tanggal' => $tanggal->toDateString(),
                ]);
            }

            $produk->decrement('stok', $jumlah);
        }
    }

    private function consumePayrollLimit(Penjualan $penjualan, Anggota $anggota, CarbonImmutable $tanggal, int $grandTotal, ?int $userId): PemakaianPotongGaji
    {
        $limit = $this->potongGajiService->findLimitFor($anggota, $tanggal);

        if (! $limit) {
            throw new RuntimeException('Limit payroll hilang saat checkout.');
        }

        return PemakaianPotongGaji::query()->create([
            'limit_potong_gaji_anggota_id' => $limit->id,
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'source_type' => Penjualan::class,
            'source_id' => $penjualan->id,
            'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
            'nominal' => $grandTotal . '.00',
            'status' => PemakaianPotongGaji::STATUS_CONSUMED,
            'idempotency_key' => 'pos:ledger:' . $penjualan->id,
            'occurred_at' => $tanggal,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function recordNonPayrollCashIn(Penjualan $penjualan, DompetKoperasi $dompet, int $grandTotal, CarbonImmutable $tanggal): void
    {
        MutasiKas::query()->create([
            'idempotency_key' => 'pos:mutasi:' . $penjualan->id,
            'dompet_id' => $dompet->id,
            'tipe' => 'masuk',
            'jumlah' => $grandTotal . '.00',
            'keterangan' => 'Penerimaan dari penjualan ' . $penjualan->kode_transaksi,
            'referensi_tipe' => Penjualan::class,
            'referensi_id' => $penjualan->id,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $dompet->update([
            'saldo' => ($this->rupiahInt($dompet->saldo) + $grandTotal) . '.00',
        ]);
    }

    private function nextKodePenjualan(CarbonImmutable $tanggal): string
    {
        $periode = $tanggal->format('Ym');
        $jenis = 'penjualan';

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor Penjualan tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('PJL-%s-%06d', $periode, $next);
    }

    private function normalizeTanggal(CarbonInterface|string|null $tanggal): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        if ($tanggal instanceof CarbonInterface) {
            return CarbonImmutable::instance($tanggal)->setTimezone($timezone);
        }

        if ($tanggal === null || trim((string) $tanggal) === '') {
            return CarbonImmutable::now($timezone);
        }

        return CarbonImmutable::parse((string) $tanggal, $timezone)->setTimezone($timezone);
    }

    private function rupiahInt(int|string|null $value): int
    {
        $normalized = trim((string) ($value ?? 0));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);

        if ((int) $fraction !== 0) {
            throw ValidationException::withMessages(['nominal' => 'Nominal harus berupa Rupiah bulat.']);
        }

        $rupiah = (int) $whole;

        return $negative ? -1 * $rupiah : $rupiah;
    }
}
