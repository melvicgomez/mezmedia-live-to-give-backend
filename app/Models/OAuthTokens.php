<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthTokens extends Model
{
    use HasFactory;
    protected $table = 'oauth_access_tokens';
    protected $primaryKey = 'id';

    protected $fillable = [
        'revoked',
    ];
}
