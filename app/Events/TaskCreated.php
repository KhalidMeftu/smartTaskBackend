<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $assignedUsers;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->assignedUsers = $task->users()->pluck('id')->toArray();
    }

    public function broadcastOn()
    {
        return collect($this->assignedUsers)->map(function ($userId) {
            return new PrivateChannel('task.create.user.' . $userId);
        })->toArray();
    }

    public function broadcastAs()
    {
        return 'task.created';
    }
}
