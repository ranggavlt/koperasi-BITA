<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
class DanaSosialSumber extends Model
{
    public const JENIS_SHU='alokasi_shu'; public const JENIS_DONASI='donasi_resmi';
    protected $table='dana_sosial_sumber'; protected $guarded=[]; protected $casts=['jumlah'=>'decimal:2','saldo_tersedia'=>'decimal:2','tanggal'=>'date','approved_at'=>'datetime'];
    protected static function booted():void{static::deleting(fn()=>throw new RuntimeException('Sumber Dana Sosial tidak boleh dihapus.'));}
    public function shu(){return $this->belongsTo(ShuKoperasi::class,'shu_koperasi_id');}
    public function dompet(){return $this->belongsTo(DompetKoperasi::class);}
    public function allocations(){return $this->hasMany(AlokasiKlaimDanaSosial::class,'dana_sosial_sumber_id');}
}
