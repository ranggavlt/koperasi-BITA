<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuAlokasi extends Model
{
    public const DANA_CADANGAN = 'dana_cadangan';
    public const DANA_SOSIAL = 'dana_sosial';

    protected $table = 'shu_alokasi';
    protected $guarded = [];
    protected $casts = ['nominal' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Alokasi SHU final tidak boleh diedit.'));
        static::deleting(fn () => throw new RuntimeException('Alokasi SHU final tidak boleh dihapus.'));
    }

    public function shu() { return $this->belongsTo(ShuKoperasi::class, 'shu_koperasi_id'); }
    public function jurnal() { return $this->belongsTo(JurnalUmum::class, 'jurnal_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
