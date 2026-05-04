<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
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
        return [
            'email' => 'required|email',
            'numero' => 'nullable|string',
            'totalPrice' => 'required|numeric',
            'type' => 'required|string|in:cart,prayer-request,subscription,tsedaka',

            'callback_url' => 'nullable|string',

            // uniquement si cart
            'cart' => 'required_if:type,cart|array',

            // uniquement si prière
            'objet' => 'required_if:type,prayer-request|string',

            // subscription
            'subscription_plan_id' => 'required_if:type,subscription|exists:subscription_plans,id',
            'password' => [
                'nullable', // Autorise explicitement la valeur null
                'string',
                // Requis uniquement pour une nouvelle inscription (registration ou full)
                Rule::requiredIf(fn() => in_array($this->payment_step, ['registration', 'full']))
            ],


            'message' => 'nullable|string',
            'montant' => 'required_if:type,tsedaka|numeric|min:100',
            'anonymous' => 'required_if:type,tsedaka|boolean',
        ];

    }
}