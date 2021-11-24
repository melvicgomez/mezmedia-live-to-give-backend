<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveSession extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'live_sessions';
    protected $primaryKey = 'live_id';

    protected $fillable = [
        'image_cover',
        'published_at',
        'interest_id',
        'user_id',
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
        'recording_link',
        'image_cover',
        'published_at',
        'scheduled_at',
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function participants()
    {
        return $this->hasMany(LiveSessionParticipant::class, 'live_id');
    }

    public function countParticipants()
    {
        return $this->hasMany(LiveSessionParticipant::class, 'live_id')
            ->whereHas('user', function ($query) {
                // uncomment line 55 if want to exclude moderator
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
        return $this->hasOne(LiveSessionOpenLogsModel::class, 'live_id');
    }
}
