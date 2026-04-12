<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalerieImage extends Model
{
    protected $fillable = [
        'dossier_id',
        'media_id',
        'titre',
        'is_visible',
        'ordre',
    ];

    public function dossier()
    {
        return $this->belongsTo(GalerieDossier::class, 'dossier_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}
