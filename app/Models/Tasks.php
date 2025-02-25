<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tasks extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'description', 'deadline', 'color', 'created_by'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id');
    }
}
