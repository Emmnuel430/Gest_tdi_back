<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['page_id', 'title', 'subtitle', 'image', 'order', 'is_visible'];
    protected $casts = [
        'is_visible' => 'boolean',
    ];
    protected $touches = ['page'];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function subsections()
    {
        return $this->hasMany(Subsection::class)->orderBy('order');
    }
}
