<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class BebanOperasionalDetail extends Model
{
    use HasFactory;

    protected $table = 'beban_operasional_detail';

    protected $fillable = [
        'beban_operasional_id',
        'akun_id',
        'aset_koperasi_id',
        'kode_akun_snapshot',
        'nama_akun_snapshot',
        'kode_aset_snapshot',
        'nama_aset_snapshot',
        'keterangan',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (BebanOperasionalDetail $detail): void {
            $detail->loadMissing('bebanOperasional');

            if ($detail->bebanOperasional && $detail->bebanOperasional->status !== BebanOperasional::STATUS_DRAFT) {
                throw new RuntimeException('Detail Beban Operasional tidak boleh dihapus setelah posted.');
            }
        });
    }

    public function bebanOperasional()
    {
        return $this->belongsTo(BebanOperasional::class, 'beban_operasional_id');
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function aset()
    {
        return $this->belongsTo(AsetKoperasi::class, 'aset_koperasi_id');
    }
}
