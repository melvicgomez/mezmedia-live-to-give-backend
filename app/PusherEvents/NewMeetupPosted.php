<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewMeetupPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $title;
    public $message;
    public $user_id;
    public $meetup_id;

    public function __construct($notification_id, $title, $message, $user_id, $meetup_id)
    {
        $this->notification_id = $notification_id;
        $this->title = $title;
        $this->message = $message;
        $this->user_id = $user_id;
        $this->meetup_id = $meetup_id;
    }

    public function broadcastOn()
    {
        return ['meetup'];
    }

    public function broadcastAs()
    {
        return 'new-meetup';
    }
}
