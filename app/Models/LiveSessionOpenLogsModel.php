<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveSessionOpenLogsModel extends Model
{
    protected $table = 'live_session_open_logs';
    protected $primaryKey = 'id';

    protected $hidden = [];

    public function challenge()
    {
        return $this->belongsTo(LiveSession::class, 'live_id');
    }
}
