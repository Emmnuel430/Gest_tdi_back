<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'type', // ['formation', 'cours']
        'access_level', // ['standard', 'premium']
        'content',
        'lien',
        'publish_at',
    ];

    protected $dates = ['publish_at'];


}
