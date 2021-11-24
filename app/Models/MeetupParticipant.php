<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetupParticipant extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'meetup_participants';
    protected $primaryKey = 'participant_id';

    protected $fillable = [
        'status',
    ];
    
    public function meetup()
    {
        return $this->belongsTo(Meetup::class, 'meetup_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
