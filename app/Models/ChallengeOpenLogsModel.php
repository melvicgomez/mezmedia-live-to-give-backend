<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeOpenLogsModel extends Model
{
    use HasFactory;

    protected $table = 'challenge_open_logs';
    protected $primaryKey = 'id';

    protected $hidden = [];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }
}
