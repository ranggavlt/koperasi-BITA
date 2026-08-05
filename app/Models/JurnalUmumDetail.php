<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class JurnalUmumDetail extends Model
{
    protected $table = 'jurnal_umum_detail';

    protected $fillable = [
        'jurnal_umum_id',
        'akun_id',
        'akun_kode',
        'akun_nama',
        'debit',
        'kredit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function jurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_umum_id');
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    protected static function booted(): void
    {
        $guard = function (self $detail): void {
            if ($detail->jurnal()->where('status', JurnalUmum::STATUS_POSTED)->exists()) {
                throw new RuntimeException('Detail jurnal posted bersifat immutable. Gunakan reversal/counter-entry.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }
}
