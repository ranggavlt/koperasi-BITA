<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class RiwayatAkunBebanOperasional extends Model
{
    protected $table = 'riwayat_akun_beban_operasional';

    protected $fillable = [
        'akun_id',
        'nilai_sebelum',
        'nilai_sesudah',
        'alasan',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'nilai_sebelum' => 'boolean',
        'nilai_sesudah' => 'boolean',
        'changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Riwayat konfigurasi Beban Operasional tidak boleh dihapus permanen.');
        });
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
