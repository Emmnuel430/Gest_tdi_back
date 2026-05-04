<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'next_payment_at' => $this->next_payment_at,
            'expires_at' => $this->ends_at,
            'remaining_months' => $this->remaining_months,
            'plan' => $this->plan ? [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'billing_type' => $this->plan->billing_type,
                'price' => $this->plan->price,
                'duration' => $this->plan->duration,
                'is_student_plan' => $this->plan->is_student_plan ? "true" : "false",
                'advantages' => $this->plan->advantages,
                'total_payments' => $this->plan->total_payments,
                'registration_fee' => $this->plan->registration_fee,
                'monthly_price' => $this->plan->monthly_price,
            ] : null,
        ];
    }
}
