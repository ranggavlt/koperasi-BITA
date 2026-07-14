<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsetPrinter extends Model
{
    use HasFactory;

    protected $table = 'aset_printer';

    protected $fillable = [
        'aset_koperasi_id',
        'nomor_seri',
        'lokasi',
    ];

    public function aset()
    {
        return $this->belongsTo(AsetKoperasi::class, 'aset_koperasi_id');
    }
}
