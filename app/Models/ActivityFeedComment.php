<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityFeedComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activity_feed_comments';
    protected $primaryKey = 'comment_id';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function flags()
    {
        return $this->hasMany(ActivityFeedCommentFlag::class, 'comment_id');
    }

    public function recentFlag()
    {
        return $this->hasOne(ActivityFeedCommentFlag::class, 'comment_id', 'comment_id')->latest();
    }

    public function activityFeed()
    {
        return $this->belongsTo(ActivityFeed::class, 'feed_id', 'feed_id');
    }
}
