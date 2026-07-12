<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class KreditPotongGajiAnggota extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIALLY_APPLIED = 'partially_applied';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'kredit_potong_gaji_anggota';

    protected $fillable = [
        'anggota_id',
        'reversal_transaksi_id',
        'nominal_awal',
        'nominal_terpakai',
        'nominal_sisa',
        'status',
        'created_by',
    ];

    protected $casts = [
        'nominal_awal' => 'decimal:2',
        'nominal_terpakai' => 'decimal:2',
        'nominal_sisa' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (KreditPotongGajiAnggota $_kredit): void {
            throw new RuntimeException('Kredit potong gaji tidak boleh dihapus permanen.');
        });
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }

    public function reversal()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }

    public function alokasi()
    {
        return $this->hasMany(AlokasiKreditPotongGaji::class, 'kredit_potong_gaji_anggota_id');
    }
}
