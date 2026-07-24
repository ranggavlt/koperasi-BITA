<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KlaimDanaKhusus extends Model
{
    use HasFactory;

    protected $table = 'klaim_dana_khusus';

    protected $fillable = [
        'dompet_id',
        'jenis_dana',
        'kategori',
        'nominal',
        'tanggal',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function dompet(): BelongsTo
    {
        return $this->belongsTo(DompetKoperasi::class, 'dompet_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
