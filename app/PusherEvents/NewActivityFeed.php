<?php

namespace App\PusherEvents;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewActivityFeed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $feed;

    public function __construct($feed)
    {
        $this->feed = $feed;
    }

    public function broadcastOn()
    {
        return ['activity-feed'];
    }

    public function broadcastAs()
    {
        return 'new-feed';
    }
}
