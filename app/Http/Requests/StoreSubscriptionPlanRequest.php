<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'billing_type' => 'required|in:one_time,monthly,hybrid',

            'price' => 'nullable|integer|min:0',
            'duration_months' => 'nullable|integer|min:1',

            'registration_fee' => 'nullable|integer|min:0',
            'monthly_price' => 'nullable|integer|min:0',
            'total_payments' => 'nullable|integer|min:1',

            'is_student_plan' => 'nullable|boolean',

            'advantages' => 'nullable|array',
        ];

        if ($this->billing_type === 'monthly') {
            $rules['price'] = 'required|integer|min:1';
        }

        if ($this->billing_type === 'one_time') {
            $rules['price'] = 'required|integer|min:1';
            $rules['duration_months'] = 'nullable|integer|min:1';
        }

        if ($this->billing_type === 'hybrid') {
            $rules['registration_fee'] = 'required|integer|min:1';
            $rules['monthly_price'] = 'required|integer|min:1';
            $rules['total_payments'] = 'required|integer|min:1';
        }

        return $rules;
    }
}
