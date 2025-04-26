<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use  Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'capital_share',
        'expected_profit',
        'collected_share',
        'paidformowner',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'capital_share' => 'decimal:2',
        'expected_profit' => 'decimal:2',
        'collected_share' => 'decimal:2',
        'paidformowner' => 'decimal:2',
    ];

}
