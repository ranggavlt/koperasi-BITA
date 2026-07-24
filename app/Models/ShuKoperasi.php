<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShuKoperasi extends Model
{
    use HasFactory;

    protected $table = 'shu_koperasi';

    protected $fillable = [
        'judul',
        'tanggal_mulai',
        'tanggal_selesai',
        'persen_dana_cadangan',
        'persen_shu_anggota',
        'persen_pengawas',
        'persen_pembina',
        'persen_pengurus',
        'persen_dana_sosial',
        'persen_dana_pendidikan',
        'persen_jasa_modal',
        'persen_jasa_usaha',
        'total_pendapatan',
        'total_biaya',
        'shu_total',
        'nominal_dana_cadangan',
        'nominal_shu_anggota',
        'nominal_pengawas',
        'nominal_pembina',
        'nominal_pengurus',
        'nominal_dana_sosial',
        'nominal_dana_pendidikan',
        'nominal_jasa_modal',
        'nominal_jasa_usaha',
        'total_bobot_modal',
        'total_bobot_usaha',
        'dihitung_pada',
        'keterangan',
        'json_pengurus_split',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'dihitung_pada' => 'datetime',
        'json_pengurus_split' => 'array',
        'persen_dana_cadangan' => 'decimal:2',
        'persen_shu_anggota' => 'decimal:2',
        'persen_pengawas' => 'decimal:2',
        'persen_pembina' => 'decimal:2',
        'persen_pengurus' => 'decimal:2',
        'persen_dana_sosial' => 'decimal:2',
        'persen_dana_pendidikan' => 'decimal:2',
        'persen_jasa_modal' => 'decimal:2',
        'persen_jasa_usaha' => 'decimal:2',
        'total_pendapatan' => 'decimal:2',
        'total_biaya' => 'decimal:2',
        'shu_total' => 'decimal:2',
        'nominal_dana_cadangan' => 'decimal:2',
        'nominal_shu_anggota' => 'decimal:2',
        'nominal_pengawas' => 'decimal:2',
        'nominal_pembina' => 'decimal:2',
        'nominal_pengurus' => 'decimal:2',
        'nominal_dana_sosial' => 'decimal:2',
        'nominal_dana_pendidikan' => 'decimal:2',
        'nominal_jasa_modal' => 'decimal:2',
        'nominal_jasa_usaha' => 'decimal:2',
        'total_bobot_modal' => 'decimal:2',
        'total_bobot_usaha' => 'decimal:2',
    ];

    public function transaksi()
    {
        return $this->hasMany(ShuTransaksi::class, 'shu_koperasi_id');
    }

    public function anggotaPembagian()
    {
        return $this->hasMany(ShuAnggota::class, 'shu_koperasi_id');
    }
}
