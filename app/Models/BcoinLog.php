<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BcoinLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bcoin_logs';
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'user_id',
        'amount',
        'description',
        'challenge_id',
        'meetup_id',
        'live_id',
        'deleted_at',
    ];

    protected $hidden = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notification()
    {
        return $this->hasOne(Notifications::class, 'transaction_id');
    }
}
