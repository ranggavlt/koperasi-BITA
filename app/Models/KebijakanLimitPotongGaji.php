<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class KebijakanLimitPotongGaji extends Model
{
    protected $table = 'kebijakan_limit_potong_gaji';

    protected $fillable = ['perusahaan_id', 'limit_nominal', 'berlaku_mulai', 'berlaku_sampai', 'aktif', 'kode_perusahaan_snapshot', 'nama_perusahaan_snapshot', 'alasan', 'created_by', 'idempotency_key'];

    protected $casts = ['limit_nominal' => 'decimal:2', 'berlaku_mulai' => 'date', 'berlaku_sampai' => 'date', 'aktif' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Kebijakan limit payroll merupakan histori dan tidak boleh dihapus.'));
    }

    public function perusahaan() { return $this->belongsTo(Perusahaan::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
