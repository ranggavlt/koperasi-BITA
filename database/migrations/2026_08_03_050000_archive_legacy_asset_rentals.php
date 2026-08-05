<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sewa_mobil')
            || ! Schema::hasTable('aset_koperasi')
            || ! Schema::hasTable('aset_mobil')
            || ! Schema::hasColumn('sewa_mobil', 'model_sumber')) {
            return;
        }

        $legacyRows = DB::table('sewa_mobil as s')
            ->join('aset_koperasi as a', 'a.id', '=', 's.aset_koperasi_id')
            ->join('aset_mobil as m', 'm.aset_koperasi_id', '=', 'a.id')
            ->whereNotNull('s.aset_koperasi_id')
            ->select([
                's.id',
                'a.kode_aset',
                'a.merek',
                'a.model',
                'm.plat_nomor',
                'm.tahun',
                'm.warna',
            ])
            ->get();

        foreach ($legacyRows as $row) {
            $plate = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $row->plat_nomor));

            DB::table('sewa_mobil')->where('id', $row->id)->update([
                'model_sumber' => 'legacy_aset',
                'vendor_nama' => 'Arsip aset internal '.$row->kode_aset,
                'vendor_kontak' => '-',
                'vendor_alamat' => 'Snapshot historis sebelum penerapan alur vendor-first.',
                'jenis_kendaraan' => 'Mobil',
                'merek_kendaraan' => $row->merek,
                'model_kendaraan' => $row->model,
                'plat_nomor_snapshot' => $row->plat_nomor,
                'plat_nomor_normalized' => $plate !== '' ? $plate : null,
                'tahun_kendaraan' => $row->tahun,
                'warna_kendaraan' => $row->warna,
                'keterangan_kendaraan' => 'Arsip transaksi Sewa Mobil berbasis aset internal.',
                'vendor_nama_snapshot' => 'Arsip aset internal '.$row->kode_aset,
                'kendaraan_jenis_snapshot' => 'Mobil',
                'kendaraan_merk_tipe_snapshot' => trim($row->merek.' '.$row->model),
                'nomor_polisi_snapshot' => $row->plat_nomor,
                'aset_koperasi_id' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Arsip tidak dikembalikan ke relasi aset agar histori tetap aman dan mandiri.
    }
};
