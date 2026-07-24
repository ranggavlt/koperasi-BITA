<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    protected $table = 'perusahaan';

    protected $fillable = [
        'kode',
        'nama',
    ];

    public function karyawan()
    {
        return $this->hasMany(Karyawan::class, 'perusahaan_id');
    }
}
