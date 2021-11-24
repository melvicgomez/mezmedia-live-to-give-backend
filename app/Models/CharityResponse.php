<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CharityResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'charity_user_response';
    protected $primaryKey = 'response_id';


    public function users()
    {
        return $this->hasOne(User::class, 'user_id');
    }
}
