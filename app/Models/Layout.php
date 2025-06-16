<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layout extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiche_titre',
        'affiche_image',
        'affiche_lien',
        'actif',
    ];
}
