<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuggestionFormChallengeModel extends Model
{
    use HasFactory;

    protected $table = 'form_suggestion_challenge';
    protected $primaryKey = 'id';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function interest()
    {
        return $this->belongsTo(ClubInterest::class, 'interest_id');
    }
}
