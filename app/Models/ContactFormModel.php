<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactFormModel extends Model
{
    use HasFactory;

    protected $table = 'form_contact_support';
    protected $primaryKey = 'contact_form_id';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
