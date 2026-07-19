<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use App\Models\SiklusKeanggotaan;
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
                if ($locked->status_kerja === Karyawan::STATUS_BERHENTI) {
                    $this->assertSettlementCompletedBeforeReactivation($locked);
                }

                $data['tanggal_berhenti'] = null;
            }

            $locked->update($data);

            if ($locked->status_kerja === Karyawan::STATUS_BERHENTI) {
                $locked->user()->update([
                    'is_active' => false,
                    'account_updated_by' => auth()->id(),
                    'account_deactivated_by' => auth()->id(),
                    'account_deactivated_at' => now(),
                ]);

                if (class_exists(SewaMobil::class)) {
                    $locked->sewaMobil()
                        ->whereIn('status', [
                            SewaMobil::STATUS_DRAFT,
                            SewaMobil::STATUS_DIAJUKAN,
                            SewaMobil::STATUS_DISETUJUI,
                        ])
                        ->update([
                            'needs_finance_review' => true,
                            'updated_by' => auth()->id(),
                        ]);
                }

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

                    $tanggalKeluar = $locked->tanggal_berhenti ?? today();
                    $lifecycle = app(KeanggotaanLifecycleService::class);
                    $cycle = $lifecycle->closeActiveCycleForExit(
                        $anggota->fresh(),
                        $tanggalKeluar,
                        auth()->id(),
                        'Karyawan berhenti.'
                    );
                    $lifecycle->createPenyelesaianForExit(
                        $anggota->fresh(),
                        $cycle,
                        $tanggalKeluar,
                        'Penyelesaian otomatis karena Karyawan berhenti.',
                        auth()->id()
                    );
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
            $cycle = app(KeanggotaanLifecycleService::class)
                ->ensureActiveCycle($anggota, auth()->id(), $data['tanggal_bergabung']);
            $this->createSimpananPokokForAnggota($anggota, $cycle);

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

            app(KeanggotaanLifecycleService::class)->closeActiveCycleForExit(
                $locked->fresh(),
                $locked->tanggal_nonaktif,
                auth()->id(),
                'Anggota dinonaktifkan.'
            );

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

            app(KeanggotaanLifecycleService::class)
                ->reactivateAnggota($locked, today(), auth()->id());

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

    private function assertSettlementCompletedBeforeReactivation(Karyawan $karyawan): void
    {
        $anggota = $karyawan->anggota()
            ->with(['siklusKeanggotaan.penyelesaian'])
            ->first();

        if (! $anggota) {
            return;
        }

        $latestClosed = $anggota->siklusKeanggotaan
            ->where('status', SiklusKeanggotaan::STATUS_CLOSED)
            ->sortByDesc('siklus_ke')
            ->first();

        if (! $latestClosed) {
            return;
        }

        if (! $latestClosed->penyelesaian || $latestClosed->penyelesaian->status !== \App\Models\PenyelesaianKeanggotaan::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status_kerja' => 'Karyawan tidak dapat diaktifkan kembali sebelum penyelesaian keanggotaan sebelumnya completed.',
            ]);
        }
    }

    private function createSimpananPokokForAnggota(Anggota $anggota, ?SiklusKeanggotaan $cycle = null): void
    {
        $cycle ??= app(KeanggotaanLifecycleService::class)
            ->ensureActiveCycle($anggota, auth()->id(), $anggota->tanggal_bergabung);

        $jenis = $this->resolveSimpananPokokMaster();

        $existing = Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $cycle->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
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
            'idempotency_key' => 'simpanan-pokok:siklus:' . $cycle->id,
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'siklus_keanggotaan_id' => $cycle->id,
            'jenis_simpanan_id' => $jenis->id,
            'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_POKOK,
            'nama_jenis_snapshot' => $jenis->nama_jenis,
            'nominal_snapshot' => $nominal,
            'jumlah' => $nominal,
            'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
            'status' => Simpanan::STATUS_PENDING_PAYROLL,
            'tanggal' => $cycle->tanggal_mulai ?? $anggota->tanggal_bergabung,
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
            ->where('kategori', JenisSimpanan::KATEGORI_POKOK)
            ->where('aktif', true)
            ->get();

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
}
