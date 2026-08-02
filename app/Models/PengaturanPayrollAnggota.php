<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PengaturanPayrollAnggota extends Model
{
    protected $table = 'pengaturan_payroll_anggota';

    protected $fillable = ['anggota_id', 'berlaku_mulai', 'limit_override_nominal', 'kredit_waserba_aktif', 'alasan', 'created_by', 'idempotency_key'];

    protected $casts = ['berlaku_mulai' => 'date', 'limit_override_nominal' => 'decimal:2', 'kredit_waserba_aktif' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Pengaturan payroll Anggota merupakan histori dan tidak boleh dihapus.'));
    }

    public function anggota() { return $this->belongsTo(Anggota::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
