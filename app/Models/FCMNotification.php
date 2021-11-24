<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FCMNotification extends Model
{
    use HasFactory;

    protected $table = 'user_fcm_tokens';
    protected $primaryKey = 'id';

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $fillable = [
        'user_id',
        'fcm_token'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
