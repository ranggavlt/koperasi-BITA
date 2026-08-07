<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuConfig extends Model
{
    protected $table = 'shu_config';
    protected $guarded = [];
    protected $casts = [
        'berlaku_mulai' => 'date',
        'persen_dana_cadangan' => 'decimal:2',
        'persen_shu_anggota' => 'decimal:2',
        'persen_pengurus' => 'decimal:2',
        'persen_pengawas' => 'decimal:2',
        'persen_pembina' => 'decimal:2',
        'persen_dana_sosial' => 'decimal:2',
        'persen_dana_pendidikan' => 'decimal:2',
        'persen_jasa_modal' => 'decimal:2',
        'persen_jasa_usaha' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Versi Pengaturan SHU tidak boleh diubah. Buat versi baru.'));
        static::deleting(fn () => throw new RuntimeException('Versi Pengaturan SHU tidak boleh dihapus.'));
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function snapshot(): array
    {
        $snapshot = $this->only([
            'versi', 'berlaku_mulai', 'dasar_keputusan', 'persen_dana_cadangan',
            'persen_shu_anggota', 'persen_pengurus', 'persen_pengawas',
            'persen_pembina', 'persen_dana_sosial', 'persen_jasa_modal', 'persen_jasa_usaha',
        ]);
        $snapshot['persen_dana_pendidikan'] = '0.00';

        return $snapshot;
    }
}
