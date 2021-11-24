<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewFeedCommentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $user_id;
    public $message;
    public $comment;
    public $feed_type;
    public $feed_id;

    public function __construct($notification_id, $user_id, $message, $comment, $feed_type, $feed_id)
    {
        $this->notification_id = $notification_id;
        $this->user_id = $user_id;
        $this->message = $message;
        $this->comment = $comment;
        $this->feed_type = $feed_type;
        $this->feed_id = $feed_id;
    }

    public function broadcastOn()
    {
        return ['activity-feed'];
    }

    public function broadcastAs()
    {
        return 'new-comment';
    }
}
