<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityFeedCommentFlag extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activity_feed_comment_flags';
    protected $primaryKey = 'flag_id';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
