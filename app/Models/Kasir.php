<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kasir extends Model
{
    /** @use HasFactory<\Database\Factories\KasirFactory> */
    use HasFactory;

    protected $fillable = [
        'nama_kasir',
        'email',
        'password',
    ];
}
