<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CharityImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'charity_images';
    protected $primaryKey = 'image_id';


    public function charity()
    {
        return $this->belongsTo(Charity::class, 'charity_id');
    }
}
