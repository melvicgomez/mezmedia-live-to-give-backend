<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StravaRecord extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'strava_records';
    protected $primaryKey = 'record_id';

    protected $fillable = [
        'strava_id',
        'user_id',
        'type',
        'name',
        'distance',
        'duration',
        'calories',
        'start_date',
        'start_date_local',
        'timezone',
        'manual',
        'external_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
