<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AlokasiKlaimDanaSosial extends Model
{
    protected $table = 'alokasi_klaim_dana_sosial';
    protected $guarded = [];
    protected $casts = ['jumlah' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Alokasi pencairan Dana Sosial tidak boleh diubah.'));
        static::deleting(fn () => throw new RuntimeException('Alokasi pencairan Dana Sosial tidak boleh dihapus.'));
    }

    public function claim() { return $this->belongsTo(KlaimDanaSosial::class, 'klaim_dana_sosial_id'); }
    public function source() { return $this->belongsTo(DanaSosialSumber::class, 'dana_sosial_sumber_id'); }
}
