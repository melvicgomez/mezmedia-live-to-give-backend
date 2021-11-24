<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChallengeParticipant extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'challenge_participants';
    protected $primaryKey = 'participant_id';

    protected $fillable = [
        'status',
        'team_id'
    ];


    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team()
    {
        return $this->belongsTo(ChallengeTeam::class, 'team_id');
    }

    public function progress()
    {
        return $this->hasMany(ChallengeParticipantProgress::class, 'participant_id');
    }

    public function stravaProgress()
    {
        return $this->hasMany(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'strava');
    }

    public function googleFitProgress()
    {
        return $this->hasMany(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'google_fit');
    }

    public function healthkitProgress()
    {
        return $this->hasMany(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'healthkit');
    }

    public function fitbitProgress()
    {
        return $this->hasMany(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'fitbit');
    }

    public function lastSyncStrava()
    {
        return $this->hasOne(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'strava')
            ->orderBy('created_at', 'desc');
    }

    public function lastSyncGoogleFit()
    {
        return $this->hasOne(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'google_fit')
            ->orderBy('created_at', 'desc');
    }

    public function lastSyncHealthKit()
    {
        return $this->hasOne(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'healthkit')
            ->orderBy('created_at', 'desc');
    }

    public function lastSyncFitbit()
    {
        return $this->hasOne(ChallengeParticipantProgress::class, 'participant_id')
            ->where('source', 'fitbit')
            ->orderBy('created_at', 'desc');
    }
}
