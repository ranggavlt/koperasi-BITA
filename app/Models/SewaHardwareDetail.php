<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SewaHardwareDetail extends Model
{
    use HasFactory;

    public const MARGIN_PERSEN = 15;

    public const JENIS_PRINTER = 'printer';

    public const JENIS_LAPTOP = 'laptop';

    public const JENIS_KAMERA = 'kamera';

    public const JENIS_LAINNYA = 'lainnya';

    protected $table = 'sewa_hardware_detail';

    protected $fillable = [
        'sewa_hardware_id',
        'jenis_hardware',
        'nama_model_hardware',
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
        static::deleting(function (SewaHardwareDetail $detail): void {
            $detail->loadMissing('sewaHardware');

            if ($detail->sewaHardware && $detail->sewaHardware->status !== SewaHardware::STATUS_DRAFT) {
                throw new RuntimeException('Detail Sewa Hardware tidak boleh dihapus setelah kontrak dikonfirmasi.');
            }
        });
    }

    public static function jenisOptions(): array
    {
        return [
            self::JENIS_PRINTER => 'Printer',
            self::JENIS_LAPTOP => 'Laptop',
            self::JENIS_KAMERA => 'Kamera',
            self::JENIS_LAINNYA => 'Lainnya',
        ];
    }

    public function sewaHardware()
    {
        return $this->belongsTo(SewaHardware::class, 'sewa_hardware_id');
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
