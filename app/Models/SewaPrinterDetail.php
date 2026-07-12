<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SewaPrinterDetail extends Model
{
    use HasFactory;

    public const MARGIN_PERSEN = 15;

    protected $table = 'sewa_printer_detail';

    protected $fillable = [
        'sewa_printer_id',
        'aset_koperasi_id',
        'kode_aset_snapshot',
        'nomor_seri_snapshot',
        'merek_snapshot',
        'model_snapshot',
        'harga_dasar',
        'margin_persen_snapshot',
        'margin_nominal',
        'total_harga',
    ];

    protected $casts = [
        'harga_dasar' => 'decimal:2',
        'margin_persen_snapshot' => 'decimal:2',
        'margin_nominal' => 'decimal:2',
        'total_harga' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SewaPrinterDetail $detail): void {
            $detail->loadMissing('sewaPrinter');

            if ($detail->sewaPrinter && $detail->sewaPrinter->status !== SewaPrinter::STATUS_DRAFT) {
                throw new RuntimeException('Detail Sewa Printer tidak boleh dihapus setelah kontrak dikonfirmasi.');
            }
        });
    }

    public function sewaPrinter()
    {
        return $this->belongsTo(SewaPrinter::class, 'sewa_printer_id');
    }

    public function aset()
    {
        return $this->belongsTo(AsetKoperasi::class, 'aset_koperasi_id');
    }
}
