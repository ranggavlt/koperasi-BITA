<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    /** @use HasFactory<\Database\Factories\KaryawanFactory> */
    use HasFactory;

    protected $table = 'karyawan';

    protected $fillable = [
        'nama',
        'email',
        'telepon',
        'jabatan',
        'is_anggota',
    ];

    protected $casts = [
        'is_anggota' => 'boolean',
    ];
}
