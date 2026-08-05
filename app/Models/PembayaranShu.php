<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
class PembayaranShu extends Model
{
    protected $table='pembayaran_shu'; protected $guarded=[]; protected $casts=['jumlah'=>'decimal:2','tanggal_bayar'=>'date'];
    protected static function booted():void{static::deleting(fn()=>throw new RuntimeException('Pembayaran SHU final tidak boleh dihapus.'));}
    public function penerima(){return $this->belongsTo(ShuPenerima::class,'shu_penerima_id');}
    public function dompet(){return $this->belongsTo(DompetKoperasi::class);}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function mutasiKas(){return $this->morphMany(MutasiKas::class,'referensi','referensi_tipe','referensi_id');}
    public function jurnal(){return $this->morphMany(JurnalUmum::class,'referensi','referensi_tipe','referensi_id');}
}
