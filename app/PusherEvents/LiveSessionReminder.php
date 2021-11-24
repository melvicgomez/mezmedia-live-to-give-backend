<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LiveSessionReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $title;
    public $message;
    public $user_id;
    public $live_id;

    public function __construct($notification_id, $title, $message, $user_id, $live_id)
    {
        $this->notification_id = $notification_id;
        $this->title = $title;
        $this->message = $message;
        $this->user_id = $user_id;
        $this->live_id = $live_id;
    }

    public function broadcastOn()
    {
        return ['live-session'];
    }

    public function broadcastAs()
    {
        return 'starting-reminder-live-session';
    }
}
