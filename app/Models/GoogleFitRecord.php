<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleFitRecord extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'google_fit_records';
    protected $primaryKey = 'record_id';

    protected $fillable = [
        'user_id',
        'type',
        'distance',
        'duration',
        'calories',
        'start_date',
        'end_date',
        'is_manual',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
