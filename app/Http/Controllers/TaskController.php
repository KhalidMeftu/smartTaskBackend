<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use App\Events\TaskUpdated;
use App\Events\TaskEditing;
use App\Events\TaskCreated;
use App\Events\TaskDeleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\User;
class TaskController extends Controller
{
    
/**
 * @OA\Get(
 *     path="/api/gettasks",
 *     summary="Get User's Tasks",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="List of tasks")
 * )
 */
public function index()
{
    $user = Auth::user();

    $tasks = Tasks::where('created_by', $user->id)
        ->orWhereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id); 
        })
        ->with('users')
        ->get();

    return response()->json([
        'tasks' => $tasks
    ]);
}



    ///create task 
    /** @OA\Post(
        *     path="/api/tasks",
        *     summary="Create a New Task",
        *     tags={"Tasks"},
        *     security={{"sanctum":{}}},
        *     @OA\RequestBody(
        *         required=true,
        *         @OA\JsonContent(
        *             required={"title"},
        *             @OA\Property(property="title", type="string", example="Task Title"),
        *             @OA\Property(property="description", type="string", example="Task description"),
        *             @OA\Property(property="deadline", type="string", format="date-time", example="2025-03-10 12:00:00"),
        *             @OA\Property(property="color", type="string", example="#ff0000")
        *         )
        *     ),
        *     @OA\Response(response=201, description="Task created successfully")
        * )
        */
        public function store(Request $request)
        {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'deadline' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
            ]);
        
            try {
                \DB::beginTransaction();
        
                // Create the task
                $task = Tasks::create([
                    'title' => $validatedData['title'],
                    'description' => $validatedData['description'] ?? null,
                    'deadline' => $validatedData['deadline'] ?? null,
                    'status' => 'pending',
                    'start_date' => $validatedData['start_date'] ?? null,
                    'end_date' => $validatedData['end_date'] ?? null,
                    'created_by' => Auth::id(),
                ]);
        
                // Attach assigned users
                $task->users()->sync($validatedData['user_ids']);
        
                \DB::commit();

                $users = User::whereIn('users.id', $validatedData['user_ids'])
                ->leftJoin('user_preferences', 'users.id', '=', 'user_preferences.user_id')
                ->where(function ($query) {
                    $query->whereNull('user_preferences.notifications')
                          ->orWhere('user_preferences.notifications', true); 
                })
                ->whereNotNull('users.fcm_token') 
                ->pluck('users.fcm_token');
                if ($users->isNotEmpty()) {
                    $this->sendFirebaseNotification($users, $task);
                }
        
                // Broadcast with users included
                broadcast(new TaskCreated($task->load('users')))->toOthers();
        
                return response()->json([
                    'message' => 'Task created successfully',
                    'task' => $task,
                ], 201);
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error("Task creation failed: " . $e->getMessage());
                return response()->json(['error' => 'Failed to create task'], 500);
            }
        }
        
        

    //update
    /**
 * @OA\Put(
 *     path="/api/tasks/{task}",
 *     summary="Update a Task",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         description="ID of the task to update",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string", example="Updated Task Title"),
 *             @OA\Property(property="description", type="string", example="Updated description"),
 *             @OA\Property(property="deadline", type="string", format="date-time", example="2025-03-15 14:00:00"),
 *             @OA\Property(property="color", type="string", example="#00ff00")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Task updated successfully"),
 *     @OA\Response(response=404, description="Task not found")
 * )
 */
public function update(Request $request, Tasks $task)
{
    $validatedData = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'deadline' => 'nullable|date',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
        'user_ids' => 'required|array',
        'user_ids.*' => 'exists:users,id',
    ]);

    try {
        \DB::beginTransaction();

        // Update task details
        $task->update([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? null,
            'deadline' => $validatedData['deadline'] ?? null,
            'start_date' => $validatedData['start_date'] ?? null,
            'end_date' => $validatedData['end_date'] ?? null,
        ]);

        //update assigned users
        $task->users()->sync($validatedData['user_ids']);

        \DB::commit();

        ///broadcast the updated task to others
        broadcast(new TaskUpdated($task->load('users')))->toOthers();

        return response()->json([
            'message' => 'Task updated successfully',
            'task' => $task->load('users'),
        ]);
    } catch (\Exception $e) {
        \DB::rollBack();
        \Log::error("Task update failed: " . $e->getMessage());

        return response()->json(['error' => 'Failed to update task'], 500);
    }
}

    // delete
    /**
 * @OA\Delete(
 *     path="/api/tasks/{task}",
 *     summary="Delete a Task",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         description="ID of the task to delete",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Task deleted successfully"),
 *     @OA\Response(response=404, description="Task not found")
 * )
 */
public function destroy($id)
{
    $task = Tasks::with('users')->find($id);

    if (!$task) {
        return response()->json(['message' => 'Task not found'], 404);
    }

    $taskId = $task->id;
    $userIds = $task->users->pluck('id')->toArray();

    $task->delete();

    broadcast(new TaskDeleted($taskId, $userIds))->toOthers();

    return response()->json(['message' => 'Task deleted']);
}

    // send notifiaction to users user is editing task
    /**
 * @OA\Post(
 *     path="/api/tasks/{task}/editing",
 *     summary="Notify when a user is editing a task",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         description="ID of the task being edited",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Editing notification sent")
 * )
 */
    public function editingTask(Tasks $task)
    {
        broadcast(new TaskEditing(Auth::user(), $task))->toOthers();

        return response()->json(['message' => 'Editing notification sent']);
    }

    public function editing(Request $request, $id)
    {
        $task = Tasks::with('users')->find($id);

    if (!$task) {
        return response()->json(['message' => 'Task not found'], 404);
    }

    $username = $request->user()->name ?? 'Unknown User';
    $userIds = $task->users->pluck('id')->toArray();

    broadcast(new TaskEditing($task->id, $username, $userIds))->toOthers();

    return response()->json(['message' => "$username is editing the task"]);
}
    

    /**
 * @OA\Post(
 *     path="/api/tasks/{task}/assign",
 *     summary="Assign Users to a Task",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"user_ids"},
 *             @OA\Property(property="user_ids", type="array", @OA\Items(type="integer"))
 *         )
 *     ),
 *     @OA\Response(response=200, description="Users assigned successfully")
 * )
 */
    public function assignTask(Request $request, Tasks $task)
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
/**
 * @OA\Post(
 *     path="/api/tasks/{task}/complete",
 *     summary="Mark a Task as Completed",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         description="ID of the task to mark as completed",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Task marked as completed")
 * )
 */
    public function markTaskComplete(Tasks $task)
    {
        $task->update(['status' => 'completed']);

        /// notify assigned users
        $users = $task->users()->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
        if (!empty($users)) {
            $this->sendFcmNotification($users, "Task Completed", "The task '{$task->title}' has been marked as completed.");
        }

        return response()->json(['message' => 'Task marked as completed']);
    }
/**
 * @OA\Patch(
 *     path="/api/tasks/{task}/status",
 *     summary="Update Task Status",
 *     tags={"Tasks"},
 *     security={{"sanctum":{}}}, 
 *     @OA\Parameter(
 *         name="task",
 *         in="path",
 *         required=true,
 *         description="ID of the task to update",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(property="status", type="string", enum={"pending", "inprogress", "completed"}, description="New status of the task")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Task status updated successfully"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */
    public function updateStatus(Request $request, Task $task)
{
    $validatedData = $request->validate([
        'status' => 'required|in:pending,inprogress,completed',
    ]);

    // Ensure only assigned users can update the status
    if (!$task->users->contains(Auth::id())) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $task->update(['status' => $validatedData['status']]);

    // Broadcast the status update event
    broadcast(new TaskStatusUpdated($task))->toOthers();

    return response()->json([
        'message' => 'Task status updated successfully',
        'task' => $task
    ]);
}




/**
 * Send Firebase Notification
 */
private function sendFirebaseNotification($tokens, $task)
{
    $credentialsPath = config('firebase.credentials');

    if (!$credentialsPath || !file_exists(base_path($credentialsPath))) {
        throw new \Exception("Firebase credentials file not found: " . base_path($credentialsPath));
    }

    $firebase = (new Factory)->withServiceAccount(base_path($credentialsPath));
    $messaging = $firebase->createMessaging();

    $notification = Notification::create(
        "New Task Assigned", 
        "You have been assigned a new task: {$task->title}"
    );

    foreach ($tokens as $token) {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData([
                'task_id' => $task->id,
                'title' => $task->title,
                'description' => $task->description ?? '',
            ]);

        $messaging->send($message);
    }
}

}
