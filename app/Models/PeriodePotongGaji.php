<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodePotongGaji extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $table = 'periode_potong_gaji';

    protected $fillable = [
        'periode',
        'status',
        'created_by',
        'updated_by',
        'activated_by',
        'closed_by',
        'activated_at',
        'closed_at',
    ];

    protected $casts = [
        'periode' => 'date',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function limits()
    {
        return $this->hasMany(LimitPotongGajiAnggota::class, 'periode_potong_gaji_id');
    }
}
