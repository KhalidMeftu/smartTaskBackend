<?php

namespace App\Events;

use App\Models\Tasks;
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

    public function __construct(Tasks $task)
    {
        $this->task = $task->load('users');
        $this->assignedUsers = $task->users()->pluck('id')->toArray();
        if (!in_array($task->created_by, $this->assignedUsers)) {
            $this->assignedUsers[] = $task->created_by;
        }
    }

    public function broadcastOn()
    {
        return collect($this->assignedUsers)->map(function ($userId) {
            return new Channel('task.create.user.' . $userId);
        })->toArray();
    }

    public function broadcastAs()
    {
        return 'task.created';
    }

    public function broadcastWith()
    {
        return [
            'task' => $this->task->toArray(),
            'assignedUsers' => $this->assignedUsers, // Full user details
        ];
    }
}

