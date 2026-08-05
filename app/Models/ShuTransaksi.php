<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuTransaksi extends Model
{
    use HasFactory;

    protected $table = 'shu_transaksi';

    protected $fillable = [
        'shu_koperasi_id',
        'jenis',
        'tanggal',
        'jumlah',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Sumber transaksi SHU tidak boleh dihapus permanen.'));
    }

    public function shuKoperasi()
    {
        return $this->belongsTo(ShuKoperasi::class, 'shu_koperasi_id');
    }
}
