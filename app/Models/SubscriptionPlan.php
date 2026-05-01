<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'billing_type',
        'price',
        'duration_months',
        'is_student_plan',
        'registration_fee',
        'monthly_price',
        'total_payments',
        'advantages',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_months' => 'integer',
        'is_student_plan' => 'boolean',
        'registration_fee' => 'integer',
        'monthly_price' => 'integer',
        'total_payments' => 'integer',
        'advantages' => 'array',
    ];

    /**
     * Relations
     */
    public function contents()
    {
        return $this->belongsToMany(Content::class, 'content_subscription_plan');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // Helpers

    public function isMonthly()
    {
        return $this->billing_type === 'monthly';
    }

    public function isOneTime()
    {
        return $this->billing_type === 'one_time';
    }

    public function isHybrid()
    {
        return $this->billing_type === 'hybrid';
    }

    /**
     * Scopes utiles
     */
    public function scopeStudents($query)
    {
        return $query->where('is_student_plan', true);
    }

    public function scopeClassic($query)
    {
        return $query->where('is_student_plan', false);
    }
}
