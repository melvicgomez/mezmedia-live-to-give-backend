<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'charities';
    protected $primaryKey = 'charity_id';

    protected $fillable =[
        'charity_name',
        'description',
        'html_content',
        'country_code',
        'user_id',
        'bcoin_donated',
    ];

    public function images()
    {
        return $this->hasMany(CharityImage::class, 'charity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}