<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubInterest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_interests';
    protected $primaryKey = 'interest_id';

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $fillable = [
        'image_cover',
        'club_id',
        'interest_name',
        'html_content',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function members()
    {
        return $this->hasMany(UserClubInterest::class, 'interest_id', 'interest_id');
    }

    public function relatedPosts()
    {
        return $this->hasMany(ActivityFeed::class, 'interest_id')
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->whereDoesntHave('flags');
    }

    public function participatedChallenges()
    {
        return $this->hasManyThrough(ChallengeParticipant::class, Challenge::class, 'interest_id', 'challenge_id', 'interest_id')
            ->whereNotNull('published_at');
    }

    public function participatedLiveSessions()
    {
        return $this->hasManyThrough(LiveSessionParticipant::class, LiveSession::class, 'interest_id', 'live_id', 'interest_id')
            ->whereNotNull('published_at');
    }

    public function participatedMeetups()
    {
        return $this->hasManyThrough(MeetupParticipant::class, Meetup::class, 'interest_id', 'meetup_id', 'interest_id')
            ->whereNotNull('published_at');
    }


    public function getRelatedChallenges()
    {
        return $this->hasMany(Challenge::class, 'interest_id')
            ->whereNotNull('published_at');
    }

    public function getRelatedLiveSessions()
    {

        return $this->hasMany(LiveSession::class, 'interest_id')
            ->whereNotNull('published_at');
    }

    public function getRelatedMeetups()
    {
        return $this->hasMany(Meetup::class, 'interest_id')
            ->whereNotNull('published_at');
    }
}
