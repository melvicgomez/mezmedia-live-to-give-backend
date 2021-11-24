<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewFeedLike implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification_id;
    public $user_id;
    public $message;
    public $source_user_id;
    public $feed_id;
    public $likeCount;
    public $feed_type;

    public function __construct($notification_id, $user_id, $message, $source_user_id, $feed_id, $likeCount, $feed_type)
    {
        $this->notification_id = $notification_id;
        $this->user_id = $user_id;
        $this->message = $message;
        $this->source_user_id = $source_user_id;
        $this->feed_id = $feed_id;
        $this->likeCount = $likeCount;
        $this->feed_type = $feed_type;
    }

    public function broadcastOn()
    {
        return ['activity-feed'];
    }

    public function broadcastAs()
    {
        return 'new-like';
    }
}
