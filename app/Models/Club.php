<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clubs';
    protected $primaryKey = 'club_id';


    public function interests()
    {
        return $this->hasMany(ClubInterest::class, 'club_id');
    }
}
