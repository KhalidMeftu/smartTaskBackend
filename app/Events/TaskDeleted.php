<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $taskId;
    public $userIds;

    public function __construct($taskId, $userIds)
    {
        $this->taskId = $taskId;
        $this->userIds = $userIds;
    }

    public function broadcastOn()
    {
        return collect($this->userIds)->map(fn($id) => new PrivateChannel("userdelete.{$id}"))->toArray();
    }

    public function broadcastAs()
    {
        return 'task.deleted';
    }
}
