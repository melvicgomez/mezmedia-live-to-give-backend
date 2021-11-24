<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityFeedImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activity_feed_images';
    protected $primaryKey = 'image_id';
}
