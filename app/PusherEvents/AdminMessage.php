<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AdminMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $title;
    public $message;
    public $user_id;
    public $deep_link;

    public function __construct($notification_id, $title, $message, $user_id, $deep_link)
    {
        $this->notification_id = $notification_id;
        $this->title = $title;
        $this->message = $message;
        $this->user_id = $user_id;
        $this->deep_link = $deep_link;
    }

    public function broadcastOn()
    {
        return ['admin'];
    }

    public function broadcastAs()
    {
        return 'admin-message';
    }
}
