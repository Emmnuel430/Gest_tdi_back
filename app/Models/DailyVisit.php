<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyVisit extends Model
{
    use HasFactory;
    protected $fillable = ['date', 'count'];

}
