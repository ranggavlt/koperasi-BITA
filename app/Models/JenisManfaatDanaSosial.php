<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class JenisManfaatDanaSosial extends Model
{
    public const KODE = [
        'MENINGGAL', 'MELAHIRKAN', 'KHITAN', 'MENIKAH', 'SANTUNAN_ANGGOTA',
    ];

    protected $table = 'jenis_manfaat_dana_sosial';
    protected $fillable = ['kode', 'nama', 'is_active', 'created_by'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Jenis manfaat Dana Sosial tidak boleh dihapus.'));
    }

    public function kebijakan() { return $this->hasMany(KebijakanManfaatDanaSosial::class, 'jenis_manfaat_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
