<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserClubInterest extends Model
{
    use HasFactory;
    protected $table = 'user_club_interests';
    protected $primaryKey = ['user_id', 'interest_id'];
    public $incrementing = false;

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    public function interest()
    {
        return $this->belongsTo(ClubInterest::class, 'interest_id')
            ->select(['interest_id', 'interest_name', 'club_id']);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
