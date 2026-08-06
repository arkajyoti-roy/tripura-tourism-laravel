<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admin';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'email',
        'password',
        'updationDate'
    ];

    protected $hidden = [
        'password',
    ];
}
