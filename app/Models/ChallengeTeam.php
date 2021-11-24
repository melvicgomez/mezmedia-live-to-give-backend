<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChallengeTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'challenge_teams';
    protected $primaryKey = 'team_id';

    protected $fillable = [
        'is_private',
        'team_code',
        'team_name',
    ];

    public function participants()
    {
        return $this->hasMany(ChallengeParticipant::class, 'team_id')
            ->whereHas('user', function ($query) {
                $query
                    // ->where('privilege', 'user')
                    ->where('is_verified', 1);
            });
    }

    public function participantCount()
    {
        return $this->hasMany(ChallengeParticipant::class, 'team_id')->count();
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }
}
