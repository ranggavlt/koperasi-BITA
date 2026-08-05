<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
class ShuPenerima extends Model
{
    public const BELUM_DIBAYAR='belum_dibayar'; public const DIBAYAR='dibayar';
    protected $table='shu_penerima'; protected $guarded=[];
    protected $casts=['bobot'=>'decimal:3','basis_jasa_modal'=>'decimal:2','basis_jasa_usaha'=>'decimal:2','nominal_jasa_modal'=>'decimal:2','nominal_jasa_usaha'=>'decimal:2','nominal_hak'=>'decimal:2','formula_snapshot'=>'array'];
    protected static function booted():void
    {
        static::updating(function (self $recipient): void {
            $protected = ['jenis_penerima','anggota_id','pengurus_koperasi_id','nama_snapshot','jabatan_snapshot','bobot','basis_jasa_modal','basis_jasa_usaha','nominal_jasa_modal','nominal_jasa_usaha','nominal_hak','formula_snapshot'];
            if ($recipient->isDirty($protected) && in_array($recipient->shu?->status, [ShuKoperasi::STATUS_SUBMITTED, ShuKoperasi::STATUS_APPROVED, ShuKoperasi::STATUS_READY_TO_PAY, ShuKoperasi::STATUS_COMPLETED], true)) {
                throw new RuntimeException('Penerima, jabatan, bobot, dan nominal SHU yang sudah diajukan tidak dapat diubah.');
            }
        });
        static::deleting(function (self $recipient): void {
            if (! in_array($recipient->shu?->status, [ShuKoperasi::STATUS_DRAFT, ShuKoperasi::STATUS_CALCULATED], true)) {
                throw new RuntimeException('Penerima SHU historis yang sudah diajukan tidak boleh dihapus.');
            }
        });
    }
    public function shu(){return $this->belongsTo(ShuKoperasi::class,'shu_koperasi_id');}
    public function anggota(){return $this->belongsTo(Anggota::class);}
    public function pengurus(){return $this->belongsTo(PengurusKoperasi::class,'pengurus_koperasi_id');}
    public function pembayaran(){return $this->hasOne(PembayaranShu::class,'shu_penerima_id');}
    public function getStatusLabelAttribute():string{return $this->status_pembayaran===self::DIBAYAR?'Sudah Dibayar':'Belum Dibayar';}
}
