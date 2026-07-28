<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'vendor';

    protected $fillable = [
        'nama',
        'kontak',
        'alamat',
    ];

    public function aset()
    {
        return $this->hasMany(AsetKoperasi::class, 'vendor_id');
    }
}
