<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
class DanaSosialSumber extends Model
{
    public const JENIS_SHU='alokasi_shu'; public const JENIS_DONASI='donasi_resmi'; public const JENIS_TAMBAHAN=self::JENIS_DONASI;
    public const STATUS_DRAFT='draft'; public const STATUS_ACTIVE='active'; public const STATUS_CLOSED='closed'; public const STATUS_REVERSED='reversed';
    protected $table='dana_sosial_sumber'; protected $guarded=[]; protected $casts=['jumlah'=>'decimal:2','nominal_awal'=>'decimal:2','saldo_tersedia'=>'decimal:2','tanggal'=>'date','tanggal_diterima'=>'date','approved_at'=>'datetime','reversed_at'=>'datetime'];
    protected static function booted():void{static::deleting(fn()=>throw new RuntimeException('Sumber Dana Sosial tidak boleh dihapus.'));}
    public function shu(){return $this->belongsTo(ShuKoperasi::class,'shu_koperasi_id');}
    public function shuKoperasi(){return $this->belongsTo(ShuKoperasi::class,'shu_koperasi_id');}
    public function dompet(){return $this->belongsTo(DompetKoperasi::class);}
    public function allocations(){return $this->hasMany(AlokasiKlaimDanaSosial::class,'dana_sosial_sumber_id');}
    public function claims(){return $this->hasMany(KlaimDanaSosial::class,'sumber_dana_sosial_id');}
    public function mutations(){return $this->hasMany(MutasiDanaSosial::class,'dana_sosial_sumber_id');}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function approver(){return $this->belongsTo(User::class,'approved_by');}
    public function reverser(){return $this->belongsTo(User::class,'reversed_by');}
    public function reversalJournal(){return $this->belongsTo(JurnalUmum::class,'reversal_journal_id');}
}
