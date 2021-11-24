<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChallengeParticipantProgress extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'challenge_participant_progress';
    protected $primaryKey = 'progress_id';

    public function participant()
    {
        return $this->belongsTo(ChallengeParticipant::class, 'participant_id');
    }
}
