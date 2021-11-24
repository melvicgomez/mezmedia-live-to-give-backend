<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FavoriteUsers extends Model
{
    use HasFactory;

    protected $table = 'user_favorites';
    protected $primaryKey = 'favorite_id';
    

public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    
    public function favoriteUser()
    {
        return $this->belongsTo(User::class, 'favorite_user_id');
    }

}
