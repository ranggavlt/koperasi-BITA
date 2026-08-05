<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
class KlaimDanaSosial extends Model
{
    public const DRAFT='draft'; public const MENUNGGU='menunggu_persetujuan'; public const DISETUJUI='disetujui'; public const DIBAYAR='dibayar';
    public const STATUS_DRAFT=self::DRAFT; public const STATUS_DIAJUKAN='diajukan'; public const STATUS_DISETUJUI=self::DISETUJUI; public const STATUS_DITOLAK='ditolak'; public const STATUS_PAID='paid'; public const STATUS_REVERSED='reversed';
    public const KATEGORI=['meninggal','melahirkan','khitan','proposal_sosial'];
    protected $table='klaim_dana_sosial'; protected $guarded=[]; protected $casts=['tanggal_kejadian'=>'date','tanggal_pengajuan'=>'date','nominal_diajukan'=>'decimal:2','nominal'=>'decimal:2','batas_nominal_snapshot'=>'decimal:2','batas_berlaku_snapshot'=>'date','submitted_at'=>'datetime','approved_at'=>'datetime','rejected_at'=>'datetime','tanggal_bayar'=>'date','paid_at'=>'datetime','reversed_at'=>'datetime'];
    protected static function booted():void{static::deleting(fn()=>throw new RuntimeException('Klaim Dana Sosial tidak boleh dihapus.'));}
    public function anggota(){return $this->belongsTo(Anggota::class);}
    public function karyawan(){return $this->belongsTo(Karyawan::class);}
    public function sumber(){return $this->belongsTo(DanaSosialSumber::class,'sumber_dana_sosial_id');}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function approver(){return $this->belongsTo(User::class,'approved_by');}
    public function payer(){return $this->belongsTo(User::class,'paid_by');}
    public function dompet(){return $this->belongsTo(DompetKoperasi::class);}
    public function allocations(){return $this->hasMany(AlokasiKlaimDanaSosial::class,'klaim_dana_sosial_id');}
    public function reverser(){return $this->belongsTo(User::class,'reversed_by');}
    public function mutasiDana(){return $this->hasMany(MutasiDanaSosial::class,'klaim_dana_sosial_id');}
    public function mutasiKas(){return $this->morphMany(MutasiKas::class,'referensi','referensi_tipe','referensi_id');}
    public function jurnal(){return $this->morphMany(JurnalUmum::class,'referensi','referensi_tipe','referensi_id');}
    public function batasKlaim(){return $this->belongsTo(BatasKlaimDanaSosial::class,'batas_klaim_id');}
    public function getStatusLabelAttribute():string{return match($this->status){self::MENUNGGU=>'Menunggu Persetujuan',self::DISETUJUI=>'Disetujui',self::DIBAYAR=>'Sudah Dibayar',default=>'Draft'};}
}
