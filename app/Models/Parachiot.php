<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parachiot extends Model
{
    protected $table = "parachiot";
    protected $fillable = ['titre', 'resume', 'contenu', 'date_lecture', 'fichier'];
}
