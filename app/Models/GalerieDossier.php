<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalerieDossier extends Model
{
    protected $fillable = [
        'nom',
        'description',
        'is_visible',
    ];

    public function images()
    {
        return $this->hasMany(GalerieImage::class, 'dossier_id');
    }
}
