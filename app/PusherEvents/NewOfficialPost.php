<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewOfficialPost implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $title;
    public $message;
    public $feed_id;
    public $feed_type;

    public function __construct($notification_id, $title, $message, $feed_id, $feed_type)
    {
        $this->notification_id = $notification_id;
        $this->title = $title;
        $this->message = $message;
        $this->feed_id = $feed_id;
        $this->feed_type = $feed_type;
    }

    public function broadcastOn()
    {
        return ['activity-feed'];
    }

    public function broadcastAs()
    {
        return 'new-official-post';
    }
}
