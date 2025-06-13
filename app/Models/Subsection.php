<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subsection extends Model
{
    use HasFactory;

    protected $fillable = ['section_id', 'title', 'content', 'image', 'order', 'date', 'prix'];
    protected $touches = ['section'];



    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
