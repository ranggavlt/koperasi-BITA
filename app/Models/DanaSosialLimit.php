<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DanaSosialLimit extends Model
{
    protected $table='dana_sosial_limit'; protected $guarded=[]; protected $casts=['maksimal'=>'decimal:2','is_active'=>'boolean'];
}
