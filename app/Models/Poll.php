<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poll extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'polls';
    protected $primaryKey = 'poll_id';

    protected $fillable = [
        'user_id',
        'title',
        'option_one',
        'option_two',
        'option_three',
        'option_four',
        'image_cover',
        'started_at',
        'ended_at',
        'published_at',
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function responses()
    {
        return $this->hasMany(PollUserResponse::class, 'poll_id');
    }
}
