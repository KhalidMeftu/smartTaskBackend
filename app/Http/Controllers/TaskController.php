<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
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
                'color' => 'nullable|string',
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
                    'color' => $validatedData['color'] ?? null,
                    'created_by' => Auth::id(),
                ]);
        
                // attach assigned users
                $task->users()->sync($validatedData['user_ids']);
        
                \DB::commit();
        
                broadcast(new TaskUpdated($task))->toOthers();
        
                return response()->json([
                    'message' => 'Task created successfully',
                    'task' => $task->load('users'),
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
        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'nullable|date',
            'color' => 'nullable|string'
        ]);

        $task->update($request->only('title', 'description', 'deadline', 'color'));

        //broadcast to assigned users
        broadcast(new TaskUpdated($task))->toOthers();

        return response()->json($task);
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
    $task = Tasks::with('assignedUsers')->find($id);

    if (!$task) {
        return response()->json(['message' => 'Task not found'], 404);
    }

    $taskId = $task->id;
    $userIds = $task->assignedUsers->pluck('id')->toArray();

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


}
