<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class JadwalCicilanPinjaman extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const METODE_POTONG_GAJI = 'potong_gaji';

    public const METODE_TUNAI = 'tunai';

    public const METODE_OFFSET_SIMPANAN_POKOK = 'offset_simpanan_pokok';

    protected $table = 'jadwal_cicilan_pinjaman';

    protected $fillable = [
        'pinjaman_id',
        'angsuran_ke',
        'periode',
        'nominal_pokok',
        'nominal_offset',
        'nominal_sisa',
        'status',
        'metode_penyelesaian',
        'paid_at',
    ];

    protected $casts = [
        'periode' => 'date',
        'nominal_pokok' => 'decimal:2',
        'nominal_offset' => 'decimal:2',
        'nominal_sisa' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (JadwalCicilanPinjaman $_jadwal): void {
            throw new RuntimeException('Jadwal cicilan tidak boleh dihapus permanen. Gunakan koreksi/reversal pada tahap lanjutan.');
        });

        static::updating(function (JadwalCicilanPinjaman $jadwal): void {
            if ($jadwal->getOriginal('status') === self::STATUS_PAID && $jadwal->isDirty()) {
                $dirty = array_keys($jadwal->getDirty());
                sort($dirty);
                $allowed = ['metode_penyelesaian', 'paid_at', 'status', 'updated_at'];
                sort($allowed);

                if ($dirty !== array_intersect($dirty, $allowed) || $jadwal->status !== self::STATUS_SCHEDULED) {
                    throw new RuntimeException('Jadwal cicilan yang sudah paid tidak boleh diubah. Gunakan reversal cicilan.');
                }
            }
        });
    }

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class);
    }

    public function cicilanPembayaran()
    {
        return $this->hasOne(CicilanPinjaman::class, 'jadwal_cicilan_pinjaman_id');
    }
}
