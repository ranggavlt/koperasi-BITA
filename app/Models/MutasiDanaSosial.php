<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class MutasiDanaSosial extends Model
{
    protected $table = 'mutasi_dana_sosial';
    protected $fillable = ['dana_sosial_sumber_id', 'klaim_dana_sosial_id', 'tipe', 'nominal', 'saldo_setelah', 'keterangan', 'created_by', 'idempotency_key'];
    protected $casts = ['nominal' => 'decimal:2', 'saldo_setelah' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Mutasi Dana Sosial tidak boleh diubah.'));
        static::deleting(fn () => throw new RuntimeException('Mutasi Dana Sosial tidak boleh dihapus.'));
    }

    public function sumber() { return $this->belongsTo(DanaSosialSumber::class, 'dana_sosial_sumber_id'); }
    public function klaim() { return $this->belongsTo(KlaimDanaSosial::class, 'klaim_dana_sosial_id'); }
}
