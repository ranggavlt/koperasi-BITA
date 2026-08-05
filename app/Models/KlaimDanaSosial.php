<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
class KlaimDanaSosial extends Model
{
    public const DRAFT='draft'; public const MENUNGGU='menunggu_persetujuan'; public const DISETUJUI='disetujui'; public const DIBAYAR='dibayar';
    protected $table='klaim_dana_sosial'; protected $guarded=[]; protected $casts=['tanggal_kejadian'=>'date','nominal_diajukan'=>'decimal:2','approved_at'=>'datetime','tanggal_bayar'=>'date','paid_at'=>'datetime'];
    protected static function booted():void{static::deleting(fn()=>throw new RuntimeException('Klaim Dana Sosial tidak boleh dihapus.'));}
    public function anggota(){return $this->belongsTo(Anggota::class);}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function approver(){return $this->belongsTo(User::class,'approved_by');}
    public function payer(){return $this->belongsTo(User::class,'paid_by');}
    public function dompet(){return $this->belongsTo(DompetKoperasi::class);}
    public function allocations(){return $this->hasMany(AlokasiKlaimDanaSosial::class,'klaim_dana_sosial_id');}
    public function getStatusLabelAttribute():string{return match($this->status){self::MENUNGGU=>'Menunggu Persetujuan',self::DISETUJUI=>'Disetujui',self::DIBAYAR=>'Sudah Dibayar',default=>'Draft'};}
}
