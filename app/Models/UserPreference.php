<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'two_factor_auth', 'theme_mode', 'notifications'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }}
