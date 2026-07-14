<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PenyelesaianKeanggotaanDetail extends Model
{
    public const KATEGORI_PINJAMAN = 'pinjaman';

    public const KATEGORI_CICILAN = 'cicilan';

    public const KATEGORI_POS = 'pos';

    public const KATEGORI_SIMPANAN = 'simpanan';

    public const KATEGORI_LAINNYA = 'lainnya';

    public const STATUS_OPEN = 'open';

    public const STATUS_OFFSET = 'offset';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_SETTLED_CASH = 'settled_cash';

    protected $table = 'penyelesaian_keanggotaan_detail';

    protected $fillable = [
        'penyelesaian_keanggotaan_id',
        'kategori_sumber',
        'source_type',
        'source_id',
        'nominal_kewajiban_awal',
        'nominal_offset',
        'nominal_dibayar_tunai',
        'nominal_sisa',
        'urutan_alokasi',
        'status',
    ];

    protected $casts = [
        'nominal_kewajiban_awal' => 'decimal:2',
        'nominal_offset' => 'decimal:2',
        'nominal_dibayar_tunai' => 'decimal:2',
        'nominal_sisa' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Detail penyelesaian keanggotaan tidak boleh dihapus permanen.');
        });
    }

    public function penyelesaian()
    {
        return $this->belongsTo(PenyelesaianKeanggotaan::class, 'penyelesaian_keanggotaan_id');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
