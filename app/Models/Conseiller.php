<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conseiller extends Model
{
    protected $fillable = ['nom', 'prenom', 'photo', 'role', 'description'];
}
