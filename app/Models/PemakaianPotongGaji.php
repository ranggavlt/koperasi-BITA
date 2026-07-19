<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemakaianPotongGaji extends Model
{
    use HasFactory;

    public const KATEGORI_CICILAN = 'cicilan';

    public const KATEGORI_SIMPANAN_POKOK = 'simpanan_pokok';

    public const KATEGORI_SIMPANAN_WAJIB = 'simpanan_wajib';

    public const KATEGORI_POS = 'pos';

    public const KATEGORI_JASA_PRINT = 'jasa_print';

    public const JENIS_RESERVASI = 'reservasi';

    public const JENIS_PEMAKAIAN = 'pemakaian';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_RELEASED = 'released';

    public const STATUS_REVERSED = 'reversed';

    protected $table = 'pemakaian_potong_gaji';

    protected $fillable = [
        'limit_potong_gaji_anggota_id',
        'kategori',
        'source_type',
        'source_id',
        'jenis',
        'nominal',
        'status',
        'idempotency_key',
        'occurred_at',
        'settled_at',
        'released_at',
        'reversed_at',
        'reversal_of_id',
        'reversal_transaksi_id',
        'created_by',
        'updated_by',
        'reversed_by',
        'released_by',
        'release_reason',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'occurred_at' => 'datetime',
        'settled_at' => 'datetime',
        'released_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function limit()
    {
        return $this->belongsTo(LimitPotongGajiAnggota::class, 'limit_potong_gaji_anggota_id');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals()
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function reversalTransaksi()
    {
        return $this->belongsTo(ReversalTransaksi::class, 'reversal_transaksi_id');
    }
}
