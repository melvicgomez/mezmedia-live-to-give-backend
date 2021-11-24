<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityFeed extends Model
{

    use HasFactory, SoftDeletes;

    protected $table = 'activity_feeds';
    protected $primaryKey = 'feed_id';
    protected $fillable = [
        'published_at',
        'title',
        'content',
        'html_content',
        'notification_message',
        'video_link',
        'feed_type',
        'is_official',
        'is_challenge_entry',
        'is_announcement',
        'charity_id',
        'editors_pick',
        'interest_id',
        'deleted_at',
        'scheduled_at',
        'pin_post',
    ];


    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }

    public function challengeParticipants()
    {
        return $this->hasManyThrough(ChallengeParticipant::class, ActivityFeed::class, 'feed_id', 'challenge_id', 'challenge_id');
    }

    public function liveSession()
    {
        return $this->belongsTo(LiveSession::class, 'live_id');
    }

    public function liveSessionParticipants()
    {
        return $this->hasManyThrough(LiveSessionParticipant::class, ActivityFeed::class, 'feed_id', 'live_id', 'live_id');
    }

    public function meetup()
    {
        return $this->belongsTo(Meetup::class, 'meetup_id');
    }

    public function meetupParticipants()
    {
        return $this->hasManyThrough(MeetupParticipant::class, ActivityFeed::class, 'feed_id', 'meetup_id', 'meetup_id');
    }

    public function charity()
    {
        return $this->belongsTo(Charity::class, 'charity_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }


    public function clubInterest()
    {
        return $this->belongsTo(ClubInterest::class, 'interest_id', 'interest_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(ActivityFeedComment::class, 'feed_id');
    }

    public function likes()
    {
        return $this->hasMany(ActivityFeedLike::class, 'feed_id');
    }

    public function images()
    {
        return $this->hasMany(ActivityFeedImage::class, 'feed_id');
    }

    public function flags()
    {
        return $this->hasMany(ActivityFeedFlag::class, 'feed_id');
    }

    public function recentFlag()
    {
        return $this->hasOne(ActivityFeedFlag::class, 'feed_id')->with(['user'])->latest();
    }

    public function image()
    {
        return $this->hasOne(ActivityFeedImage::class, 'feed_id')
            ->orderBy('created_at', 'asc'); // asc or desc
    }
}
