<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DanaSosialSumber extends Model
{
    public const JENIS_SHU = 'shu';
    public const JENIS_TAMBAHAN = 'tambahan_disetujui';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'dana_sosial_sumber';
    protected $fillable = ['kode_sumber', 'nama_sumber', 'jenis_sumber', 'shu_koperasi_id', 'nominal_awal', 'saldo_tersedia', 'status', 'keterangan', 'created_by', 'approved_by', 'approved_at', 'idempotency_key'];
    protected $casts = ['nominal_awal' => 'decimal:2', 'saldo_tersedia' => 'decimal:2', 'approved_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Sumber Dana Sosial merupakan histori dan tidak boleh dihapus.'));
    }

    public function shuKoperasi() { return $this->belongsTo(ShuKoperasi::class); }
    public function claims() { return $this->hasMany(KlaimDanaSosial::class, 'sumber_dana_sosial_id'); }
    public function mutations() { return $this->hasMany(MutasiDanaSosial::class, 'dana_sosial_sumber_id'); }
}
