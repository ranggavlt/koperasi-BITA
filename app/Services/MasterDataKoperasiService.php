<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\PengurusKoperasi;
use App\Models\Simpanan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MasterDataKoperasiService
{
    public function createKaryawan(array $data): Karyawan
    {
        return DB::transaction(function () use ($data): Karyawan {
            if ($data['status_kerja'] === Karyawan::STATUS_AKTIF) {
                $data['tanggal_berhenti'] = null;
            }

            return Karyawan::query()->create($data);
        });
    }

    public function deleteUnusedKaryawan(Karyawan $karyawan): void
    {
        DB::transaction(function () use ($karyawan): void {
            $locked = Karyawan::query()->lockForUpdate()->findOrFail($karyawan->id);

            if ($locked->anggota()->exists() || $locked->mempunyaiTransaksi()) {
                throw ValidationException::withMessages([
                    'karyawan' => 'Karyawan yang sudah menjadi Anggota atau mempunyai transaksi tidak boleh dihapus permanen. Gunakan status berhenti.',
                ]);
            }

            $locked->delete();
        });
    }

    public function updateKaryawan(Karyawan $karyawan, array $data): Karyawan
    {
        return DB::transaction(function () use ($karyawan, $data): Karyawan {
            $locked = Karyawan::query()->lockForUpdate()->findOrFail($karyawan->id);

            if ($data['status_kerja'] === Karyawan::STATUS_AKTIF) {
                $data['tanggal_berhenti'] = null;
            }

            $locked->update($data);

            if ($locked->status_kerja === Karyawan::STATUS_BERHENTI) {
                $anggota = $locked->anggota()->lockForUpdate()->first();

                if ($anggota) {
                    $anggota->update([
                        'status' => Anggota::STATUS_NONAKTIF,
                        'tanggal_nonaktif' => $locked->tanggal_berhenti,
                    ]);

                    $anggota->pengurus()
                        ->aktif()
                        ->update([
                            'status' => PengurusKoperasi::STATUS_NONAKTIF,
                        ]);

                    app(PotongGajiBulananService::class)
                        ->releaseReservationsForStoppedAnggota($anggota->fresh(), auth()->id());
                }
            }

            $this->syncLegacyIsAnggota($locked);

            return $locked->fresh('anggota.pengurusAktif');
        });
    }

    public function createAnggota(array $data): Anggota
    {
        return DB::transaction(function () use ($data): Anggota {
            $karyawan = Karyawan::query()->lockForUpdate()->findOrFail($data['karyawan_id']);

            if ($karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
                throw ValidationException::withMessages([
                    'karyawan_id' => 'Karyawan berhenti tidak dapat didaftarkan sebagai Anggota.',
                ]);
            }

            if ($karyawan->anggota()->exists()) {
                throw ValidationException::withMessages([
                    'karyawan_id' => 'Karyawan ini sudah mempunyai data Anggota.',
                ]);
            }

            $anggota = Anggota::query()->create([
                'karyawan_id' => $karyawan->id,
                'nomor_anggota' => 'TMP-' . Str::random(16),
                'tanggal_bergabung' => $data['tanggal_bergabung'],
                'alamat' => $data['alamat'],
                'status' => Anggota::STATUS_AKTIF,
                'tanggal_nonaktif' => null,
                'plafon_pinjaman' => $data['plafon_pinjaman'],
            ]);

            $anggota->forceFill([
                'nomor_anggota' => 'AGT-' . str_pad((string) $anggota->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            $this->syncLegacyIsAnggota($karyawan);
            $this->createSimpananPokokForAnggota($anggota);

            return $anggota->fresh(['karyawan', 'simpanan']);
        });
    }

    public function updateAnggota(Anggota $anggota, array $data): Anggota
    {
        return DB::transaction(function () use ($anggota, $data): Anggota {
            $locked = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);
            $locked->update($data);
            $this->syncLegacyIsAnggota($locked->karyawan);

            return $locked->fresh('karyawan');
        });
    }

    public function deleteUnusedAnggota(Anggota $anggota): void
    {
        DB::transaction(function () use ($anggota): void {
            $locked = Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($anggota->id);

            if ($locked->mempunyaiTransaksi() || $locked->pengurus()->exists()) {
                throw ValidationException::withMessages([
                    'anggota' => 'Anggota yang mempunyai transaksi atau histori Pengurus tidak boleh dihapus permanen. Gunakan tindakan nonaktifkan.',
                ]);
            }

            $karyawan = $locked->karyawan;
            $locked->delete();
            $this->syncLegacyIsAnggota($karyawan);
        });
    }

    public function deactivateAnggota(Anggota $anggota): Anggota
    {
        return DB::transaction(function () use ($anggota): Anggota {
            $locked = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);

            if ($locked->status === Anggota::STATUS_NONAKTIF) {
                $this->syncLegacyIsAnggota($locked->karyawan);

                return $locked->fresh('karyawan');
            }

            $locked->update([
                'status' => Anggota::STATUS_NONAKTIF,
                'tanggal_nonaktif' => today(),
            ]);

            $locked->pengurus()
                ->aktif()
                ->update([
                    'status' => PengurusKoperasi::STATUS_NONAKTIF,
                ]);

            app(PotongGajiBulananService::class)
                ->releaseReservationsForStoppedAnggota($locked->fresh(), auth()->id());

            $this->syncLegacyIsAnggota($locked->karyawan);

            return $locked->fresh('karyawan');
        });
    }

    public function activateAnggota(Anggota $anggota): Anggota
    {
        return DB::transaction(function () use ($anggota): Anggota {
            $locked = Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($anggota->id);

            if ($locked->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
                throw ValidationException::withMessages([
                    'status' => 'Anggota tidak dapat diaktifkan karena Karyawannya sudah berhenti.',
                ]);
            }

            $locked->update([
                'status' => Anggota::STATUS_AKTIF,
                'tanggal_nonaktif' => null,
            ]);
            $this->syncLegacyIsAnggota($locked->karyawan);

            return $locked->fresh('karyawan');
        });
    }

    public function createPengurus(array $data): PengurusKoperasi
    {
        try {
            return DB::transaction(function () use ($data): PengurusKoperasi {
                $anggota = Anggota::query()
                    ->with('karyawan')
                    ->lockForUpdate()
                    ->findOrFail($data['anggota_id']);

                $this->assertEligiblePengurus($anggota);
                $this->assertNoActivePengurusConflict($anggota->id, $data['jabatan']);

                return PengurusKoperasi::query()->create([
                    'anggota_id' => $anggota->id,
                    'jabatan' => $data['jabatan'],
                    'status' => PengurusKoperasi::STATUS_AKTIF,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwFriendlyPengurusConflict(
                (int) $data['anggota_id'],
                (string) $data['jabatan'],
                null,
                $exception
            );
        }
    }

    public function updatePengurus(PengurusKoperasi $pengurus, array $data): PengurusKoperasi
    {
        try {
            return DB::transaction(function () use ($pengurus, $data): PengurusKoperasi {
                $locked = PengurusKoperasi::query()->lockForUpdate()->findOrFail($pengurus->id);
                $anggota = Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($data['anggota_id']);

                if ($locked->status === PengurusKoperasi::STATUS_AKTIF) {
                    $this->assertEligiblePengurus($anggota);
                    $this->assertNoActivePengurusConflict($anggota->id, $data['jabatan'], $locked->id);
                }

                $locked->update($data);

                return $locked->fresh('anggota.karyawan');
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwFriendlyPengurusConflict(
                (int) $data['anggota_id'],
                (string) $data['jabatan'],
                $pengurus->id,
                $exception
            );
        }
    }

    public function deactivatePengurus(PengurusKoperasi $pengurus): PengurusKoperasi
    {
        return DB::transaction(function () use ($pengurus): PengurusKoperasi {
            $locked = PengurusKoperasi::query()->lockForUpdate()->findOrFail($pengurus->id);
            $locked->update([
                'status' => PengurusKoperasi::STATUS_NONAKTIF,
            ]);

            return $locked->fresh('anggota.karyawan');
        });
    }

    public function activatePengurus(PengurusKoperasi $pengurus): PengurusKoperasi
    {
        try {
            return DB::transaction(function () use ($pengurus): PengurusKoperasi {
                $locked = PengurusKoperasi::query()
                    ->with('anggota.karyawan')
                    ->lockForUpdate()
                    ->findOrFail($pengurus->id);

                $this->assertEligiblePengurus($locked->anggota);
                $this->assertNoActivePengurusConflict($locked->anggota_id, $locked->jabatan, $locked->id);

                $locked->update(['status' => PengurusKoperasi::STATUS_AKTIF]);

                return $locked->fresh('anggota.karyawan');
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwFriendlyPengurusConflict(
                $pengurus->anggota_id,
                $pengurus->jabatan,
                $pengurus->id,
                $exception
            );
        }
    }

    private function assertNoActivePengurusConflict(int $anggotaId, string $jabatan, ?int $ignoreId = null): void
    {
        $anggotaConflict = PengurusKoperasi::query()->aktif()->where('anggota_id', $anggotaId);
        $jabatanConflict = PengurusKoperasi::query()->aktif()->where('jabatan', $jabatan);

        if ($ignoreId !== null) {
            $anggotaConflict->where('id', '!=', $ignoreId);
            $jabatanConflict->where('id', '!=', $ignoreId);
        }

        if ($anggotaConflict->exists()) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini sudah mempunyai jabatan Pengurus aktif.',
            ]);
        }

        if ($jabatanConflict->exists()) {
            throw ValidationException::withMessages([
                'jabatan' => 'Jabatan ini sudah diisi oleh Pengurus aktif.',
            ]);
        }
    }

    private function throwFriendlyPengurusConflict(
        int $anggotaId,
        string $jabatan,
        ?int $ignoreId,
        UniqueConstraintViolationException $_exception
    ): never {
        try {
            $this->assertNoActivePengurusConflict($anggotaId, $jabatan, $ignoreId);
        } catch (ValidationException $validationException) {
            throw $validationException;
        }

        throw ValidationException::withMessages([
            'pengurus' => 'Data Pengurus bertabrakan dengan jabatan aktif lain. Muat ulang halaman lalu coba kembali.',
        ]);
    }

    private function assertEligiblePengurus(Anggota $anggota): void
    {
        if ($anggota->status !== Anggota::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Pengurus wajib berasal dari Anggota aktif.',
            ]);
        }

        if ($anggota->karyawan->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Pengurus wajib berasal dari Karyawan aktif.',
            ]);
        }
    }

    private function syncLegacyIsAnggota(Karyawan $karyawan): void
    {
        $aktif = $karyawan->status_kerja === Karyawan::STATUS_AKTIF
            && $karyawan->anggota()->where('status', Anggota::STATUS_AKTIF)->exists();

        $karyawan->forceFill(['is_anggota' => $aktif])->saveQuietly();
    }

    private function createSimpananPokokForAnggota(Anggota $anggota): void
    {
        $jenis = $this->resolveSimpananPokokMaster();

        $existing = Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return;
        }

        $nominal = $jenis->nominal_default;
        if ($nominal === null || (float) $nominal <= 0) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Nominal default Simpanan Pokok aktif wajib lebih besar dari nol.',
            ]);
        }

        $simpanan = Simpanan::query()->create([
            'idempotency_key' => 'simpanan-pokok:anggota:' . $anggota->id,
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'jenis_simpanan_id' => $jenis->id,
            'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_POKOK,
            'nama_jenis_snapshot' => $jenis->nama_jenis,
            'nominal_snapshot' => $nominal,
            'jumlah' => $nominal,
            'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
            'status' => Simpanan::STATUS_PENDING_PAYROLL,
            'tanggal' => $anggota->tanggal_bergabung,
            'keterangan' => 'Simpanan Pokok otomatis saat Anggota dibuat.',
            'created_by' => auth()->id(),
        ]);

        app(AkuntansiService::class)->recordSimpananPokokPayroll($simpanan, auth()->id());
    }

    private function resolveSimpananPokokMaster(): JenisSimpanan
    {
        $active = JenisSimpanan::query()
            ->with('akun')
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->where('aktif', true)
            ->get();

        if ($active->isEmpty()) {
            $this->bootstrapSimpananPokokMaster();
            $active = JenisSimpanan::query()
                ->with('akun')
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)
                ->where('aktif', true)
                ->get();
        }

        if ($active->count() !== 1) {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Harus ada tepat satu Master Jenis Simpanan Pokok aktif.',
            ]);
        }

        $jenis = $active->first();
        $akun = $jenis->akun;

        if (! $akun || ! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages([
                'simpanan_pokok' => 'Master Simpanan Pokok wajib memiliki COA aktif kategori kewajiban/ekuitas dengan saldo normal Kredit.',
            ]);
        }

        return $jenis;
    }

    private function bootstrapSimpananPokokMaster(): void
    {
        $accountCode = config('account_map.accounts.simpanan_pokok.kode_akun');
        $akun = Akun::query()->where('kode_akun', $accountCode)->first();

        if (! $akun) {
            return;
        }

        JenisSimpanan::query()->firstOrCreate(
            ['kode' => JenisSimpanan::KODE_SIMPANAN_POKOK],
            [
                'akun_id' => $akun->id,
                'nama_jenis' => 'Simpanan Pokok',
                'wajib' => true,
                'aktif' => true,
                'nominal_default' => 100000,
                'keterangan' => 'Setoran awal otomatis saat anggota mulai aktif di koperasi.',
            ]
        );
    }
}
