<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'address',
        'email',
        'phone',
        'validDate',
        'updateDate',
        'displayorder'
    ];
}
