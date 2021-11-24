<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challenge extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'challenges';
    protected $primaryKey = 'challenge_id';

    protected $fillable = [
        'title',
        'description',
        'html_content',
        'image_cover',
        'interest_id',
        'target_goal',
        'type',
        'target_unit',
        'bcoin_reward',
        'is_team_challenge',
        'is_trackable',
        'is_editor_pick',
        'is_featured',
        'started_at',
        'registration_ended_at',
        'ended_at',
        'published_at',
        'duration',
        'notification_message'
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function participants()
    {
        return $this->hasMany(ChallengeParticipant::class, 'challenge_id');
    }

    public function countParticipants()
    {
        return $this->hasMany(ChallengeParticipant::class, 'challenge_id')
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

    public function teams()
    {
        return $this->hasMany(ChallengeTeam::class, 'challenge_id');
    }

    public function isOpen()
    {
        return $this->hasOne(ChallengeOpenLogsModel::class, 'challenge_id');
    }

    public function entries()
    {
        return $this->hasMany(ActivityFeed::class, 'challenge_id')->where('is_challenge_entry', 1);
    }
}
