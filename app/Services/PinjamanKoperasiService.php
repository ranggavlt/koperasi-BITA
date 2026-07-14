<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\Pinjaman;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PinjamanKoperasiService
{
    public const MAX_PINJAMAN = 5000000;

    public function __construct(
        private readonly AkunResolver $akunResolver,
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function create(array $data, ?int $userId = null): Pinjaman
    {
        try {
            return DB::transaction(function () use ($data, $userId): Pinjaman {
                $anggota = Anggota::query()
                    ->with('karyawan')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['anggota_id']);

                $this->assertEligibleAnggota($anggota);
                $this->assertNoActiveLoan($anggota);

                $jumlahRupiah = $this->rupiahInt($data['jumlah_pinjaman']);
                $tenor = (int) $data['tenor_bulan'];

                $this->assertJumlahValid($jumlahRupiah, $anggota);
                $this->assertTenorValid($tenor);
                $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);

                $dompet = DompetKoperasi::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['dompet_id']);

                $akunDompet = $this->assertUsableDompet($dompet);
                $this->assertSaldoCukup($dompet, $jumlahRupiah);

                $tanggalPinjaman = $this->normalizeTanggal($data['tanggal_pinjaman']);
                $kodePinjaman = $this->nextKodePinjaman($tanggalPinjaman);
                $jumlahDecimal = $this->rupiahDecimal($jumlahRupiah);

                $pinjaman = Pinjaman::query()->create([
                    'kode_pinjaman' => $kodePinjaman,
                    'anggota_id' => $anggota->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'dompet_id' => $dompet->id,
                    'jumlah_pinjaman' => $jumlahDecimal,
                    'plafon_pinjaman_snapshot' => $this->rupiahDecimal($this->rupiahInt($anggota->plafon_pinjaman)),
                    'bunga_persen' => '0.00',
                    'tenor_bulan' => $tenor,
                    'sisa_pinjaman' => $jumlahDecimal,
                    'status' => Pinjaman::STATUS_AKTIF,
                    'tanggal_pinjaman' => $tanggalPinjaman->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                    'created_by' => $userId,
                ]);

                $this->createJadwal($pinjaman, $jumlahRupiah, $tenor, $tanggalPinjaman);
                $this->recordMutasiPencairan($pinjaman, $dompet, $jumlahRupiah);
                $this->decreaseSaldoDompet($dompet, $jumlahRupiah);
                $this->akuntansiService->recordPencairanPinjaman($pinjaman, $akunDompet, $userId);

                return $pinjaman->fresh(['anggota.karyawan', 'dompet.akun', 'jadwalCicilan', 'mutasiKas', 'jurnal.details']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini sudah mempunyai Pinjaman aktif atau kode Pinjaman bertabrakan. Muat ulang halaman lalu coba kembali.',
            ]);
        }
    }

    /**
     * @return array<int, array{angsuran_ke:int, periode:string, nominal_pokok:string}>
     */
    public function buildJadwalPreview(int|string $jumlahPinjaman, int $tenor, CarbonInterface|string $tanggalPinjaman): array
    {
        $jumlahRupiah = $this->rupiahInt($jumlahPinjaman);
        $this->assertTenorValid($tenor);
        $this->assertJumlahCukupUntukTenor($jumlahRupiah, $tenor);
        $tanggal = $this->normalizeTanggal($tanggalPinjaman);

        $base = intdiv($jumlahRupiah, $tenor);
        $remainder = $jumlahRupiah % $tenor;
        $periode = $tanggal->startOfMonth()->addMonth();
        $rows = [];

        for ($i = 1; $i <= $tenor; $i++) {
            $nominal = $base + ($i === $tenor ? $remainder : 0);
            $rows[] = [
                'angsuran_ke' => $i,
                'periode' => $periode->copy()->addMonths($i - 1)->toDateString(),
                'nominal_pokok' => $this->rupiahDecimal($nominal),
            ];
        }

        return $rows;
    }

    private function createJadwal(Pinjaman $pinjaman, int $jumlahRupiah, int $tenor, CarbonImmutable $tanggalPinjaman): void
    {
        $rows = collect($this->buildJadwalPreview($jumlahRupiah, $tenor, $tanggalPinjaman))
            ->map(fn (array $row) => $row + [
                'pinjaman_id' => $pinjaman->id,
                'nominal_offset' => '0.00',
                'nominal_sisa' => $row['nominal_pokok'],
                'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
                'metode_penyelesaian' => null,
                'paid_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        JadwalCicilanPinjaman::query()->insert($rows);
    }

    private function recordMutasiPencairan(Pinjaman $pinjaman, DompetKoperasi $dompet, int $jumlahRupiah): MutasiKas
    {
        return MutasiKas::query()->create([
            'idempotency_key' => $this->pencairanIdempotencyKey($pinjaman, 'mutasi'),
            'dompet_id' => $dompet->id,
            'tipe' => 'keluar',
            'jumlah' => $this->rupiahDecimal($jumlahRupiah),
            'keterangan' => 'Pencairan pinjaman ' . $pinjaman->kode_pinjaman,
            'referensi_tipe' => Pinjaman::class,
            'referensi_id' => $pinjaman->id,
            'tanggal' => $pinjaman->tanggal_pinjaman,
        ]);
    }

    private function decreaseSaldoDompet(DompetKoperasi $dompet, int $jumlahRupiah): void
    {
        $saldoBaru = $this->rupiahInt($dompet->saldo) - $jumlahRupiah;

        $dompet->update([
            'saldo' => $this->rupiahDecimal($saldoBaru),
        ]);
    }

    public function pencairanIdempotencyKey(Pinjaman $pinjaman, string $jenis): string
    {
        return "pinjaman:pencairan:{$jenis}:{$pinjaman->id}";
    }

    private function assertEligibleAnggota(Anggota $anggota): void
    {
        if ($anggota->status !== Anggota::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Pinjaman hanya dapat dibuat untuk Anggota aktif.',
            ]);
        }

        if ($anggota->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Pinjaman hanya dapat dibuat untuk Karyawan aktif.',
            ]);
        }
    }

    private function assertNoActiveLoan(Anggota $anggota): void
    {
        if (Pinjaman::query()->where('anggota_id', $anggota->id)->where('status', Pinjaman::STATUS_AKTIF)->exists()) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini masih mempunyai Pinjaman aktif.',
            ]);
        }
    }

    private function assertJumlahValid(int $jumlahRupiah, Anggota $anggota): void
    {
        if ($jumlahRupiah <= 0) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman harus lebih besar dari nol.',
            ]);
        }

        if ($jumlahRupiah > self::MAX_PINJAMAN) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman maksimal Rp5.000.000.',
            ]);
        }

        $plafon = $this->rupiahInt($anggota->plafon_pinjaman);

        if ($jumlahRupiah > $plafon) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman melebihi plafon Anggota.',
            ]);
        }
    }

    private function assertTenorValid(int $tenor): void
    {
        if ($tenor < 1 || $tenor > 12) {
            throw ValidationException::withMessages([
                'tenor_bulan' => 'Tenor pinjaman minimal 1 bulan dan maksimal 12 bulan.',
            ]);
        }
    }

    private function assertJumlahCukupUntukTenor(int $jumlahRupiah, int $tenor): void
    {
        if ($jumlahRupiah < $tenor) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Jumlah pinjaman terlalu kecil untuk tenor yang dipilih.',
            ]);
        }
    }

    private function assertUsableDompet(DompetKoperasi $dompet): Akun
    {
        $akun = $dompet->akun;

        if (! $akun) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet sumber pencairan belum memiliki mapping COA.',
            ]);
        }

        if (! $akun->is_aktif || $akun->kategori !== 'aset' || $akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => 'Akun Dompet harus aktif, kategori Aset, dan saldo normal Debit.',
            ]);
        }

        $this->akunResolver->posting('pinjaman.piutang');

        return $akun;
    }

    private function assertSaldoCukup(DompetKoperasi $dompet, int $jumlahRupiah): void
    {
        if ($this->rupiahInt($dompet->saldo) < $jumlahRupiah) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Saldo Dompet tidak mencukupi untuk pencairan Pinjaman.',
            ]);
        }
    }

    private function nextKodePinjaman(CarbonImmutable $tanggalPinjaman): string
    {
        $periode = $tanggalPinjaman->format('Ym');
        $jenis = 'pinjaman';

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            //
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor Pinjaman tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('PJM-%s-%06d', $periode, $next);
    }

    private function normalizeTanggal(CarbonInterface|string $tanggal): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        if ($tanggal instanceof CarbonInterface) {
            return CarbonImmutable::instance($tanggal)->setTimezone($timezone)->startOfDay();
        }

        return CarbonImmutable::parse((string) $tanggal, $timezone)->setTimezone($timezone)->startOfDay();
    }

    private function rupiahInt(int|string $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);

        if ((int) $fraction !== 0) {
            throw ValidationException::withMessages([
                'jumlah_pinjaman' => 'Nominal pinjaman harus berupa Rupiah bulat.',
            ]);
        }

        $rupiah = (int) $whole;

        return $negative ? -1 * $rupiah : $rupiah;
    }

    private function rupiahDecimal(int $value): string
    {
        return $value . '.00';
    }
}
