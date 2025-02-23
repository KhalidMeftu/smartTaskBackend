<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Events\TaskUpdated;
use App\Events\TaskEditing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\User;
class TaskController extends Controller
{
    //

    public function index()
    {
        return response()->json(Auth::user()->tasks);
    }

    ///create task 
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'nullable|date',
            'color' => 'nullable|string'
        ]);

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'deadline' => $request->deadline,
            'color' => $request->color,
            'created_by' => Auth::id()
        ]);

        broadcast(new TaskUpdated($task))->toOthers();

        return response()->json($task);
    }

    //update
    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'nullable|date',
            'color' => 'nullable|string'
        ]);

        $task->update($request->only('title', 'description', 'deadline', 'color'));

        // Broadcast to assigned users
        broadcast(new TaskUpdated($task))->toOthers();

        return response()->json($task);
    }
    // delete
    public function destroy(Task $task)
    {
        $task->delete();

        broadcast(new TaskUpdated($task))->toOthers();

        return response()->json(['message' => 'Task deleted']);
    }
    // send notifiaction to users user is editing task
    public function editingTask(Task $task)
    {
        broadcast(new TaskEditing(Auth::user(), $task))->toOthers();

        return response()->json(['message' => 'Editing notification sent']);
    }

    public function assignTask(Request $request, Task $task)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        // for multiple users
        $task->users()->sync($request->user_ids);

        // fetch tokes
        $users = User::whereIn('id', $request->user_ids)->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

        if (!empty($users)) {
            $this->sendFcmNotification($users, "New Task Assigned", "You have been assigned a new task: {$task->title}");
        }

        return response()->json(['message' => 'Task assigned successfully']);
    }

    private function sendFcmNotification($tokens, $title, $body)
    {
        $factory = (new Factory)->withServiceAccount(base_path('firebase_credentials.json'));
        $messaging = $factory->createMessaging();

        $notification = Notification::create($title, $body);

        foreach ($tokens as $token) {
            $message = CloudMessage::withTarget('token', $token)->withNotification($notification);
            $messaging->send($message);
        }
    }

    public function markTaskComplete(Task $task)
    {
        $task->update(['status' => 'completed']);

        /// notify assigned users
        $users = $task->users()->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
        if (!empty($users)) {
            $this->sendFcmNotification($users, "Task Completed", "The task '{$task->title}' has been marked as completed.");
        }

        return response()->json(['message' => 'Task marked as completed']);
    }


}
