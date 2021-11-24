<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetupOpenLogsModel extends Model
{
    protected $table = 'meetup_open_logs';
    protected $primaryKey = 'id';

    protected $hidden = [];

    public function challenge()
    {
        return $this->belongsTo(Meetup::class, 'meetup_id');
    }
}
