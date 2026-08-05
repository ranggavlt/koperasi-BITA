<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AlokasiKlaimDanaSosial extends Model
{
    protected $table='alokasi_klaim_dana_sosial'; protected $guarded=[]; protected $casts=['jumlah'=>'decimal:2'];
    public function source(){return $this->belongsTo(DanaSosialSumber::class,'dana_sosial_sumber_id');}
}
