<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DompetKoperasi extends Model
{
    use HasFactory;

    public const JENIS_KAS = 'kas';

    public const JENIS_BANK = 'bank';

    protected $table = 'dompet_koperasi';

    protected $fillable = [
        'akun_id',
        'nama_dompet',
        'jenis_dompet',
        'is_default_penerimaan_payroll',
        'is_kas_operasional',
        'default_payroll_marker',
        'saldo',
    ];

    protected $casts = [
        'is_default_penerimaan_payroll' => 'boolean',
        'is_kas_operasional' => 'boolean',
        'saldo' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (DompetKoperasi $dompet): void {
            if ($dompet->is_default_penerimaan_payroll && $dompet->jenis_dompet !== self::JENIS_BANK) {
                throw new RuntimeException('Dompet default penerimaan payroll harus berjenis bank.');
            }

            if (DB::connection()->getDriverName() !== 'mysql') {
                $dompet->default_payroll_marker = $dompet->is_default_penerimaan_payroll
                    && $dompet->jenis_dompet === self::JENIS_BANK
                    ? 1
                    : null;
            }
        });
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function pinjaman()
    {
        return $this->hasMany(Pinjaman::class, 'dompet_id');
    }

    public function scopeKas($query)
    {
        return $query->where('jenis_dompet', self::JENIS_KAS);
    }

    public function scopeBank($query)
    {
        return $query->where('jenis_dompet', self::JENIS_BANK);
    }

    public function scopeDefaultPayroll($query)
    {
        return $query->where('is_default_penerimaan_payroll', true);
    }
}
