<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'file_type',
        'hash',
    ];

    protected $appends = ['url'];

    // A utiliser si vous passez sur un stockage cloud (AWS S3, Google Cloud Storage)
    // public function getUrlAttribute()
    // {
    //     return Storage::url($this->file_path);
    // }

    // asset() génère une URL absolue basée sur le domaine
    public function getUrlAttribute()
    {
        return asset(Storage::url($this->file_path));
    }

    // Combien de fois le media est attribué à une image
    public function galerieImages()
    {
        return $this->hasMany(GalerieImage::class);
    }
}
