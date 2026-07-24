<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\ShuAnggota;
use App\Models\ShuKoperasi;
use App\Models\ShuTransaksi;
use App\Models\Simpanan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShuKoperasiService
{
    public function create(array $data): ShuKoperasi
    {
        return DB::transaction(function () use ($data) {
            $shuKoperasi = ShuKoperasi::create($data);

            return $this->refresh($shuKoperasi);
        });
    }

    public function update(ShuKoperasi $shuKoperasi, array $data): ShuKoperasi
    {
        return DB::transaction(function () use ($shuKoperasi, $data) {
            $shuKoperasi->update($data);

            return $this->refresh($shuKoperasi);
        });
    }

    public function addTransaksi(ShuKoperasi $shuKoperasi, array $data): ShuTransaksi
    {
        return DB::transaction(function () use ($shuKoperasi, $data) {
            $transaksi = $shuKoperasi->transaksi()->create($data);

            $this->refresh($shuKoperasi->fresh());

            return $transaksi;
        });
    }

    public function deleteTransaksi(ShuTransaksi $shuTransaksi): void
    {
        DB::transaction(function () use ($shuTransaksi) {
            $shuKoperasi = $shuTransaksi->shuKoperasi;

            $shuTransaksi->delete();

            if ($shuKoperasi) {
                $this->refresh($shuKoperasi->fresh());
            }
        });
    }

    public function refresh(ShuKoperasi $shuKoperasi): ShuKoperasi
    {
        return DB::transaction(function () use ($shuKoperasi) {
            $shuKoperasi->load('transaksi');

            $totalPendapatan = round((float) $shuKoperasi->transaksi
                ->where('jenis', 'pendapatan')
                ->sum('jumlah'), 2);

            $totalBiaya = round((float) $shuKoperasi->transaksi
                ->where('jenis', 'biaya')
                ->sum('jumlah'), 2);

            $shuTotal = round($totalPendapatan - $totalBiaya, 2);
            $basisPembagian = max(0, $shuTotal);

            $nominalShuAnggota = $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_shu_anggota);

            $shuKoperasi->update([
                'total_pendapatan' => $totalPendapatan,
                'total_biaya' => $totalBiaya,
                'shu_total' => $shuTotal,
                'nominal_dana_cadangan' => $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_dana_cadangan),
                'nominal_shu_anggota' => $nominalShuAnggota,
                'nominal_pengawas' => $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_pengawas),
                'nominal_pembina' => $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_pembina),
                'nominal_pengurus' => $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_pengurus),
                'nominal_dana_sosial' => $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_dana_sosial),
                'nominal_dana_pendidikan' => $this->calculateNominal($basisPembagian, (float) $shuKoperasi->persen_dana_pendidikan),
                'nominal_jasa_modal' => $this->calculateNominal($nominalShuAnggota, (float) $shuKoperasi->persen_jasa_modal),
                'nominal_jasa_usaha' => $this->calculateNominal($nominalShuAnggota, (float) $shuKoperasi->persen_jasa_usaha),
                'dihitung_pada' => now(),
            ]);

            $this->syncPembagianAnggota($shuKoperasi->fresh());

            return $shuKoperasi->fresh(['transaksi', 'anggotaPembagian.karyawan']);
        });
    }

    protected function syncPembagianAnggota(ShuKoperasi $shuKoperasi): void
    {
        $tanggalMulai = Carbon::parse($shuKoperasi->tanggal_mulai)->startOfDay();
        $tanggalSelesai = Carbon::parse($shuKoperasi->tanggal_selesai)->endOfDay();

        $anggota = Karyawan::query()
            ->with('anggota')
            ->orderBy('nama')
            ->get(['id', 'nama']);

        $totalSimpananPerAnggota = Simpanan::query()
            ->select('karyawan_id', DB::raw('SUM(jumlah) as total_simpanan'))
            ->whereBetween('tanggal', [
                $tanggalMulai->toDateString(),
                $tanggalSelesai->toDateString(),
            ])
            ->groupBy('karyawan_id')
            ->pluck('total_simpanan', 'karyawan_id');

        $totalUsahaPerAnggota = Penjualan::query()
            ->select('karyawan_id', DB::raw('SUM(grand_total) as total_usaha'))
            ->whereBetween('created_at', [$tanggalMulai, $tanggalSelesai])
            ->groupBy('karyawan_id')
            ->pluck('total_usaha', 'karyawan_id');

        $bobotModal = $anggota->mapWithKeys(function (Karyawan $karyawan) use ($totalSimpananPerAnggota) {
            return [$karyawan->id => round((float) ($totalSimpananPerAnggota[$karyawan->id] ?? 0), 2)];
        });

        $bobotUsaha = $anggota->mapWithKeys(function (Karyawan $karyawan) use ($totalUsahaPerAnggota) {
            $totalUsaha = round((float) ($totalUsahaPerAnggota[$karyawan->id] ?? 0), 2);
            return [$karyawan->id => $totalUsaha];
        });

        $alokasiJasaModal = $this->allocateProportionally(
            (float) $shuKoperasi->nominal_jasa_modal,
            $bobotModal
        );

        $alokasiJasaUsaha = $this->allocateProportionally(
            (float) $shuKoperasi->nominal_jasa_usaha,
            $bobotUsaha
        );

        ShuAnggota::query()
            ->where('shu_koperasi_id', $shuKoperasi->id)
            ->delete();

        $timestamp = now();

        $rows = $anggota->map(function (Karyawan $karyawan) use (
            $shuKoperasi,
            $bobotModal,
            $bobotUsaha,
            $alokasiJasaModal,
            $alokasiJasaUsaha,
            $timestamp
        ) {
            $nominalJasaModal = round((float) ($alokasiJasaModal[$karyawan->id] ?? 0), 2);
            $nominalJasaUsaha = round((float) ($alokasiJasaUsaha[$karyawan->id] ?? 0), 2);

            return [
                'shu_koperasi_id' => $shuKoperasi->id,
                'karyawan_id' => $karyawan->id,
                'anggota_id' => $karyawan->anggota?->id,
                'total_simpanan' => round((float) ($bobotModal[$karyawan->id] ?? 0), 2),
                'total_transaksi_usaha' => round((float) ($bobotUsaha[$karyawan->id] ?? 0), 2),
                'nominal_jasa_modal' => $nominalJasaModal,
                'nominal_jasa_usaha' => $nominalJasaUsaha,
                'nominal_shu' => round($nominalJasaModal + $nominalJasaUsaha, 2),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        })->all();

        if ($rows !== []) {
            ShuAnggota::query()->insert($rows);
        }

        $shuKoperasi->update([
            'total_bobot_modal' => round((float) $bobotModal->sum(), 2),
            'total_bobot_usaha' => round((float) $bobotUsaha->sum(), 2),
        ]);
    }

    protected function allocateProportionally(float $nominalTotal, Collection $weights): Collection
    {
        $result = $weights->mapWithKeys(fn ($value, $key) => [$key => 0.0]);
        $eligible = $weights->filter(fn ($value) => (float) $value > 0);

        $totalWeight = round((float) $eligible->sum(), 2);

        if ($nominalTotal <= 0 || $totalWeight <= 0 || $eligible->isEmpty()) {
            return $result;
        }

        $allocated = 0.0;
        $lastKey = $eligible->keys()->last();

        foreach ($eligible as $key => $weight) {
            if ($key === $lastKey) {
                $share = round($nominalTotal - $allocated, 2);
            } else {
                $share = round(($nominalTotal * (float) $weight) / $totalWeight, 2);
                $allocated += $share;
            }

            $result[$key] = $share;
        }

        return $result;
    }

    protected function calculateNominal(float $nilaiDasar, float $persen): float
    {
        return round($nilaiDasar * $persen / 100, 2);
    }
}
