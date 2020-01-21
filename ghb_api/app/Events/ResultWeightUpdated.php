<?php

namespace App\Events;

use App\Result;
use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ResultWeightUpdated extends Event
{
    use SerializesModels;

    public $result;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Result $result)
    {
        $this->result = $result;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
