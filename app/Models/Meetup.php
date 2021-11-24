<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meetup extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'meetups';
    protected $primaryKey = 'meetup_id';

    protected $fillable = [
        'published_at',
        'title',
        'notification_message',
        'description',
        'html_content',
        'bcoin_reward',
        'started_at',
        'registration_ended_at',
        'ended_at',
        'is_editor_pick',
        'is_featured',
        'slots',
        'host_name',
        'host_email',
        'additional_details',
        'virtual_room_link',
        'venue',
        'image_cover',
        'recording_link',
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function participants()
    {
        return $this->hasMany(MeetupParticipant::class, 'meetup_id');
    }

    public function countParticipants()
    {
        return $this->hasMany(MeetupParticipant::class, 'meetup_id')
            ->whereHas('user', function ($query) {
                // uncomment line 52 if want to exclude moderator
                $query
                    // ->where('privilege', 'user')
                    ->where('is_verified', 1);
            });
    }

    public function clubInterest()
    {
        return $this->hasOne(ClubInterest::class, 'interest_id', 'interest_id');
    }

    public function isOpen()
    {
        return $this->hasOne(MeetupOpenLogsModel::class, 'meetup_id');
    }
}
