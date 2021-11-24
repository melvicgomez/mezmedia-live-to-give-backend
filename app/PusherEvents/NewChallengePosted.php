<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewChallengePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $title;
    public $message;
    public $user_id;
    public $challenge_id;
    public $is_trackable;
    public $is_team_challenge;

    public function __construct($notification_id, $title, $message, $user_id, $challenge_id, $is_trackable, $is_team_challenge)
    {
        $this->notification_id = $notification_id;
        $this->title = $title;
        $this->message = $message;
        $this->user_id = $user_id;
        $this->challenge_id = $challenge_id;
        $this->is_trackable = $is_trackable;
        $this->is_team_challenge = $is_team_challenge;
    }

    public function broadcastOn()
    {
        return ['challenge'];
    }

    public function broadcastAs()
    {
        return 'new-challenge';
    }
}
