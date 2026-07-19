<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SaldoSimpananSukarela extends Model
{
    use HasFactory;

    protected $table = 'saldo_simpanan_sukarela';

    protected $fillable = [
        'anggota_id',
        'siklus_keanggotaan_id',
        'jenis_simpanan_id',
        'saldo',
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (SaldoSimpananSukarela $saldo): void {
            if ((float) $saldo->saldo < 0) {
                throw new RuntimeException('Saldo Simpanan Sukarela tidak boleh negatif.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Saldo Simpanan Sukarela tidak boleh dihapus permanen.');
        });
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function siklusKeanggotaan()
    {
        return $this->belongsTo(SiklusKeanggotaan::class, 'siklus_keanggotaan_id');
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class, 'jenis_simpanan_id');
    }
}
