<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailWhitelist extends Model
{
    use HasFactory;
    protected $table = 'email_whitelist';
    protected $primaryKey = 'id';
}
