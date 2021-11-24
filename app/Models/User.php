<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'business_area',
        'country_code',
        'description',
        'photo_url',
        'privilege',
        'is_verified'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'updated_at',
    ];

    public function userInterests()
    {
        return $this->hasMany(UserClubInterest::class, 'user_id');
    }

    public function userChallenges()
    {
        return $this->hasMany(ChallengeParticipant::class, 'user_id')->where('status', 'DONE');
    }
    public function userMeetups()
    {
        return $this->hasMany(MeetupParticipant::class, 'user_id')->where('status', 'DONE');
    }

    public function userLiveSession()
    {
        return $this->hasMany(LiveSessionParticipant::class, 'user_id')->where('status', 'DONE');
    }

    public function bcoinTotal()
    {
        return $this->hasMany(BcoinLog::class, 'user_id');
    }

    public function fcmTokens()
    {
        return $this->hasMany(FCMNotification::class, 'user_id');
    }

    public function feedPosts()
    {
        return $this->hasMany(ActivityFeed::class, 'user_id')
            ->where('feed_type', 'feed')
            ->withTrashed(); // withTrashed will also return the soft deleted feed posts
    }

    public function comments()
    {
        return $this->hasMany(ActivityFeedComment::class, 'user_id')
            ->withTrashed(); // withTrashed will also return the soft deleted feed posts
    }

    public function feedPostFlags()
    {
        return $this->hasMany(ActivityFeedFlag::class, 'user_id');
    }

    public function commentFlags()
    {
        return $this->hasMany(ActivityFeedComment::class, 'user_id');
    }


    public function activityChallenges()
    {
        return $this->hasMany(ChallengeParticipant::class, 'user_id');
    }

    public function activityMeetups()
    {
        return $this->hasMany(MeetupParticipant::class, 'user_id');
    }

    public function activityLiveSessions()
    {
        return $this->hasMany(LiveSessionParticipant::class, 'user_id');
    }

    public function favoriteUsers()
    {
        return $this->hasMany(FavoriteUsers::class, 'favorite_user_id', 'user_id');
    }

    public function charityResponse()
    {
        return $this->hasOne(CharityResponse::class, 'user_id');
    }
}
