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
        'jenis_model_printer',
        'spesifikasi_kebutuhan',
        'kuantitas',
        'harga_vendor_per_unit',
        'margin_persen_snapshot',
        'margin_per_unit',
        'harga_tagihan_per_unit',
        'subtotal_harga_vendor',
        'subtotal_margin',
        'subtotal_tagihan',
    ];

    protected $casts = [
        'kuantitas' => 'integer',
        'harga_vendor_per_unit' => 'integer',
        'margin_persen_snapshot' => 'integer',
        'margin_per_unit' => 'integer',
        'harga_tagihan_per_unit' => 'integer',
        'subtotal_harga_vendor' => 'integer',
        'subtotal_margin' => 'integer',
        'subtotal_tagihan' => 'integer',
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

    public function getHargaDasarAttribute(): int
    {
        return (int) ($this->subtotal_harga_vendor ?? 0);
    }

    public function getMarginNominalAttribute(): int
    {
        return (int) ($this->subtotal_margin ?? 0);
    }

    public function getTotalHargaAttribute(): int
    {
        return (int) ($this->subtotal_tagihan ?? 0);
    }
}
