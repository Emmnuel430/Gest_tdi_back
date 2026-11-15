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
            'nom' => 'required_if:type,prayer-request|nullable|string',
            'prenom' => 'required_if:type,prayer-request|nullable|string',
            'totalPrice' => [
                'required',
                'numeric',
                Rule::when(
                    in_array($this->type, ['tsedaka', 'prayer-request']),
                    ['min:2000'],
                    ['min:1']
                ),
            ],
            'type' => 'required|string|in:cart,prayer-request,subscription,tsedaka',

            'callback_url' => 'nullable|string',

            // uniquement si cart
            'cart' => 'required_if:type,cart|array|min:1',
            'cart.*.product_id' => 'required_with:cart|integer|exists:subsections,id',
            'cart.*.quantity' => 'required_with:cart|integer|min:1',

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

            'message' => 'required_if:type,prayer-request|nullable|string',
            'montant' => 'required_if:type,tsedaka,prayer-request|numeric|min:2000',
            'montant_tsedaka' => 'nullable|numeric|min:0',
            'anonymous' => 'required_if:type,tsedaka|boolean',
        ];
    }
}
