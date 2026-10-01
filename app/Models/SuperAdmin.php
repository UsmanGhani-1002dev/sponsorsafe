<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SuperAdmin extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'two_factor_secret', 'two_factor_confirmed_at', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'password_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_token_expires_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
        ];
    }
}
