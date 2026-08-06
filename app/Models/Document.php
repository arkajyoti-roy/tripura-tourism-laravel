<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'docid';
    public $timestamps = false;
    
    protected $fillable = [
        'des',
        'file',
        'doctype',
        'status',
        'openDate',
        'closeDate',
        'updateDate',
        'whatsnew'
    ];
}
