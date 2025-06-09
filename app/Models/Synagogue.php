<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Synagogue extends Model
{
    protected $fillable = ['nom', 'localisation', 'horaires'];
}
