<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAccessLog extends Model
{

    use HasFactory;
    protected $table = 'user_access_logs';
    protected $primaryKey = 'log_id';

    protected $hidden = ['updated_at'];
}
