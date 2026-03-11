<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DompetKoperasi extends Model
{
    protected $table = 'dompet_koperasi';

    protected $fillable = [
        'nama_dompet',
        'saldo'
    ];
}