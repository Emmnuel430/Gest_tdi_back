<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'type', // ['formation', 'cours']
        'content',
        'lien',
        'publish_at',
        'is_public',
        'is_student_only'
    ];

    protected $dates = ['publish_at'];
    protected $casts = [
        'is_public' => 'boolean',
        'is_student_only' => 'boolean'
    ];

    public static function getAvailableTypes()
    {
        return ['all', 'formation', 'cours', 'evenement']; // Ajoutez vos types ici
    }


    public function plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'content_subscription_plan');
    }


}
