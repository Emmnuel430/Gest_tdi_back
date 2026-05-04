<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'adherent_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'ends_at',
        'next_payment_at',
        'remaining_months',
    ];
    protected $guarded = ['starts_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'remaining_months' => 'integer',
        'next_payment_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function adherent()
    {
        return $this->belongsTo(Adherent::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Helpers métier
     */

    // abonnement actif ?
    public function isActive()
    {
        return $this->status === 'active'
            && (!$this->ends_at || $this->ends_at >= now());
    }

    // abonnement expiré ?
    public function isExpired()
    {
        return $this->ends_at && $this->ends_at < now();
    }

    /**
     * 🔁 Renouvellement (mensuel)
     */
    public function renewMonthly()
    {
        $this->ends_at = $this->ends_at
            ? Carbon::parse($this->ends_at)->addMonth()
            : now()->addMonth();

        $this->status = 'active';

        $this->save();
    }

    /**
     * 🎓 Cas étudiant (9 mois)
     */
    public function decrementStudentMonth()
    {
        if ($this->remaining_months === null)
            return;

        $this->remaining_months--;

        $this->ends_at = now()->addMonth();

        if ($this->remaining_months <= 0) {
            $this->status = 'expired';
        }

        $this->save();
    }

    public static function createFromPlan($adherent, $plan)
    {
        $data = [
            'adherent_id' => $adherent->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ];

        if ($plan->is_student_plan) {
            $data['remaining_months'] = $plan->total_payments;
            $data['ends_at'] = now()->endOfYear();

        } else {
            $data['ends_at'] = now()->addMonth();
        }

        return self::create($data);
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function hasPendingPayments()
    {
        return $this->remaining_months > 0;
    }
}
