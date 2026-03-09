<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPinjaman extends Model
{
    protected $table = 'jenis_pinjaman';

    protected $fillable = [
        'nama_pinjaman',
        'bunga_persen',
        'tenor_bulan',
        'keterangan'
    ];

}