<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Adventure extends Model
{
    protected $fillable = ['name', 'description', 'location', 'image', 'price', 'contact_phone', 'adventure_type'];
}
