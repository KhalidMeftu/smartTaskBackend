<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes();

        Broadcast::channel('tasks.{taskId}', function ($user, $taskId) {
            /// user is assigned to the task
            return Task::where('id', $taskId)->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->exists();
        });

        require base_path('routes/channels.php');   
     }
}
