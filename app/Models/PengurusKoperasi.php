<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengurusKoperasi extends Model
{
    use HasFactory;

    protected $table = 'pengurus_koperasi';

    protected $fillable = [
        'nama',
        'email',
        'telepon',
        'jabatan',
    ];
}
