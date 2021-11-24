<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OneTimePin extends Model
{
    use HasFactory;

    protected $table = 'one_time_pins';
    protected $primaryKey = 'otp_id';

    protected $fillable = [
        'is_used',
    ];
}
