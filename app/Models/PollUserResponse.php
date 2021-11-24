<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PollUserResponse extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'poll_user_response';
    protected $primaryKey = 'response_id';

    protected $fillable = [
        'user_id',
        'poll_id',
        'answer',
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }
}
