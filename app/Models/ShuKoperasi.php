<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ShuKoperasi extends Model
{
    use HasFactory;

    public const STATUS_DRAFT='draft';
    public const STATUS_CALCULATED='calculated';
    public const STATUS_SUBMITTED='submitted';
    public const STATUS_APPROVED='approved';
    public const STATUS_READY_TO_PAY='ready_to_pay';
    public const STATUS_COMPLETED='completed';

    protected $table = 'shu_koperasi';

    protected $fillable = [
        'periode_akuntansi_id',
        'judul',
        'periode_akuntansi_id','shu_config_id','config_snapshot','status',
        'tanggal_mulai',
        'tanggal_selesai',
        'persen_dana_cadangan',
        'persen_shu_anggota',
        'persen_pengawas',
        'persen_pembina',
        'persen_pengurus',
        'persen_dana_sosial',
        'persen_dana_pendidikan',
        'persen_jasa_modal',
        'persen_jasa_usaha',
        'total_pendapatan',
        'total_biaya',
        'shu_total',
        'nominal_dana_cadangan',
        'nominal_shu_anggota',
        'nominal_pengawas',
        'nominal_pembina',
        'nominal_pengurus',
        'nominal_dana_sosial',
        'nominal_dana_pendidikan',
        'nominal_jasa_modal',
        'nominal_jasa_usaha',
        'total_bobot_modal',
        'total_bobot_usaha',
        'dihitung_pada',
        'keterangan',
        'total_dibayar','total_belum_dibayar','created_by','calculated_by','submitted_by','approved_by','submitted_at','approved_at','completed_at','idempotency_key',
        'json_pengurus_split',
        'status',
        'config_snapshot',
        'source_snapshot',
        'closed_at',
        'closed_by',
        'idempotency_key',
        'created_by',
        'calculated_by',
        'calculated_at',
        'approved_by',
        'approved_at',
        'approval_reason',
        'allocation_journal_id',
        'posted_by',
        'posted_at',
        'reversal_journal_id',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'dihitung_pada' => 'datetime',
        'json_pengurus_split' => 'array',
        'config_snapshot'=>'array','submitted_at'=>'datetime','approved_at'=>'datetime','completed_at'=>'datetime',
        'persen_dana_cadangan' => 'decimal:2',
        'persen_shu_anggota' => 'decimal:2',
        'persen_pengawas' => 'decimal:2',
        'persen_pembina' => 'decimal:2',
        'persen_pengurus' => 'decimal:2',
        'persen_dana_sosial' => 'decimal:2',
        'persen_dana_pendidikan' => 'decimal:2',
        'persen_jasa_modal' => 'decimal:2',
        'persen_jasa_usaha' => 'decimal:2',
        'total_pendapatan' => 'decimal:2',
        'total_biaya' => 'decimal:2',
        'shu_total' => 'decimal:2',
        'nominal_dana_cadangan' => 'decimal:2',
        'nominal_shu_anggota' => 'decimal:2',
        'nominal_pengawas' => 'decimal:2',
        'nominal_pembina' => 'decimal:2',
        'nominal_pengurus' => 'decimal:2',
        'nominal_dana_sosial' => 'decimal:2',
        'nominal_dana_pendidikan' => 'decimal:2',
        'nominal_jasa_modal' => 'decimal:2',
        'nominal_jasa_usaha' => 'decimal:2',
        'total_bobot_modal' => 'decimal:2',
        'total_bobot_usaha' => 'decimal:2',
        'config_snapshot' => 'array',
        'source_snapshot' => 'array',
        'closed_at' => 'datetime',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Periode SHU adalah histori audit dan tidak boleh dihapus permanen.'));
        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === 'closed' || $model->getOriginal('closed_at') !== null) {
                $allowed = ['status', 'reversal_journal_id', 'reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];
                if (array_diff(array_keys($model->getDirty()), $allowed) !== [] || $model->status !== 'reversed') {
                    throw new RuntimeException('Periode SHU yang sudah ditutup bersifat immutable.');
                }
            }
        });
        static::updating(function (self $shu): void {
            $immutable = [
                'periode_akuntansi_id', 'shu_config_id', 'config_snapshot', 'tanggal_mulai', 'tanggal_selesai',
                'persen_dana_cadangan', 'persen_shu_anggota', 'persen_pengawas', 'persen_pembina',
                'persen_pengurus', 'persen_dana_sosial', 'persen_dana_pendidikan', 'persen_jasa_modal',
                'persen_jasa_usaha', 'total_pendapatan', 'total_biaya', 'shu_total', 'nominal_dana_cadangan',
                'nominal_shu_anggota', 'nominal_pengawas', 'nominal_pembina', 'nominal_pengurus',
                'nominal_dana_sosial', 'nominal_dana_pendidikan', 'nominal_jasa_modal', 'nominal_jasa_usaha',
                'total_bobot_modal', 'total_bobot_usaha',
            ];
            if ($shu->isDirty($immutable) && in_array($shu->getOriginal('status'), [self::STATUS_SUBMITTED, self::STATUS_APPROVED, self::STATUS_READY_TO_PAY, self::STATUS_COMPLETED], true)) {
                throw new RuntimeException('Periode, konfigurasi, basis, dan nominal SHU yang sudah diajukan tidak dapat diubah.');
            }
        });
    }

    public function transaksi()
    {
        return $this->hasMany(ShuTransaksi::class, 'shu_koperasi_id');
    }

    public function anggotaPembagian()
    {
        return $this->hasMany(ShuAnggota::class, 'shu_koperasi_id');
    }

    public function periode(){return $this->belongsTo(PeriodeAkuntansi::class,'periode_akuntansi_id');}
    public function periodeAkuntansi(){return $this->belongsTo(PeriodeAkuntansi::class,'periode_akuntansi_id');}
    public function config(){return $this->belongsTo(ShuConfig::class,'shu_config_id');}
    public function recipients(){return $this->hasMany(ShuPenerima::class,'shu_koperasi_id');}
    public function socialFund(){return $this->hasOne(DanaSosialSumber::class,'shu_koperasi_id');}
    public function socialFundSource(){return $this->hasOne(DanaSosialSumber::class,'shu_koperasi_id');}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function approver(){return $this->belongsTo(User::class,'approved_by');}
    public function calculator(){return $this->belongsTo(User::class,'calculated_by');}
    public function submitter(){return $this->belongsTo(User::class,'submitted_by');}
    public function poster(){return $this->belongsTo(User::class,'posted_by');}
    public function allocationJournal(){return $this->belongsTo(JurnalUmum::class,'allocation_journal_id');}
    public function reversalJournal(){return $this->belongsTo(JurnalUmum::class,'reversal_journal_id');}
    public function getStatusLabelAttribute():string{return match($this->status){self::STATUS_CALCULATED=>'Sudah Dihitung',self::STATUS_SUBMITTED=>'Menunggu Persetujuan',self::STATUS_APPROVED=>'Disetujui',self::STATUS_READY_TO_PAY=>'Siap Dibayar',self::STATUS_COMPLETED=>'Selesai',default=>'Draft'};}
}
