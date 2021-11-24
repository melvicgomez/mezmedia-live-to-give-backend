<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notifications extends Model
{

    use HasFactory, SoftDeletes;
    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'deep_link',
        'transaction_id',
        'challenge_id',
        'live_id',
        'meetup_id',
        'charity_id',
        'feed_id',
        'source_user_id',
        'scheduled_at',
    ];

    protected $hidden = [];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }
    // public function meetup()
    // {
    //     return $this->belongsTo(Meetup::class, 'meetup_id');
    // }
    // public function liveSession()
    // {
    //     return $this->belongsTo(LiveSession::class, 'live_id');
    // }
    public function activityFeed()
    {
        return $this->belongsTo(ActivityFeed::class, 'feed_id');
    }

    public function bcoinRecord()
    {
        return $this->belongsTo(BcoinLog::class, 'transaction_id');
    }
}
