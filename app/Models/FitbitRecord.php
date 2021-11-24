<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FitbitRecord extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'fitbit_records';
    protected $primaryKey = 'record_id';

    protected $fillable = [
        'fitbit_id',
        'user_id',
        'type',
        'distance',
        'duration',
        'calories',
        'start_date',
        'start_date_local',
        'log_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
