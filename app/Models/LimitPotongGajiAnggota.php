<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LimitPotongGajiAnggota extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED_PENDING_CONFIRMATION = 'closed_pending_confirmation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'limit_potong_gaji_anggota';

    protected $fillable = [
        'periode_potong_gaji_id',
        'anggota_id',
        'dompet_penerimaan_id',
        'limit_nominal',
        'sumber_limit_snapshot',
        'perusahaan_id_snapshot',
        'kode_perusahaan_snapshot',
        'nama_perusahaan_snapshot',
        'kredit_waserba_aktif_snapshot',
        'status',
        'activated_by',
        'confirmed_by',
        'closed_by',
        'cancelled_by',
        'activated_at',
        'closed_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'limit_nominal' => 'decimal:2',
        'kredit_waserba_aktif_snapshot' => 'boolean',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function periodePotongGaji()
    {
        return $this->belongsTo(PeriodePotongGaji::class, 'periode_potong_gaji_id');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class);
    }

    public function dompetPenerimaan()
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_penerimaan_id');
    }

    public function riwayat()
    {
        return $this->hasMany(RiwayatLimitPotongGaji::class, 'limit_potong_gaji_anggota_id');
    }

    public function pemakaian()
    {
        return $this->hasMany(PemakaianPotongGaji::class, 'limit_potong_gaji_anggota_id');
    }

    public function sisaLimitCents(): int
    {
        $used = $this->pemakaian()
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
            ])
            ->sum('nominal');

        return $this->decimalToCents($this->limit_nominal) - $this->decimalToCents((string) $used);
    }

    private function decimalToCents(int|string $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -1 * $cents : $cents;
    }
}
