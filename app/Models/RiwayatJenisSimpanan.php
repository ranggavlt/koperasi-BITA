<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class RiwayatJenisSimpanan extends Model
{
    protected $table = 'riwayat_jenis_simpanan';

    protected $fillable = [
        'jenis_simpanan_id',
        'konfigurasi_sebelum',
        'konfigurasi_sesudah',
        'alasan',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'konfigurasi_sebelum' => 'array',
        'konfigurasi_sesudah' => 'array',
        'changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Riwayat perubahan Master Jenis Simpanan tidak boleh dihapus.');
        });
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class, 'jenis_simpanan_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
