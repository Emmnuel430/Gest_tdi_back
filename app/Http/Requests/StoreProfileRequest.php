<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfileRequest extends FormRequest
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
            // ================= INFOS PERSO =================
            'adresse' => 'sometimes|nullable|string|max:255',
            'date_naissance' => 'sometimes|nullable|date',
            'situation_matrimoniale' => 'sometimes|nullable|string|max:50',
            'nombre_enfants' => 'sometimes|nullable|integer|min:0',
            'profession' => 'sometimes|nullable|string|max:100',

            // ================= CONTACT =================
            'telephone_whatsapp' => 'sometimes|nullable|string|max:20',
            'telephone_secondaire' => 'sometimes|nullable|string|max:20',

            // ================= URGENCE =================
            'urgence_nom' => 'sometimes|nullable|string|max:100',
            'urgence_numero' => 'sometimes|nullable|string|max:20',
            'urgence_lien' => 'sometimes|nullable|string|max:50',

            // ================= EDUCATION =================
            'niveau_etudes' => 'sometimes|nullable|string|max:100',
            'dernier_diplome' => 'sometimes|nullable|string|max:100',

            // ================= RELIGIEUX =================
            'etude_religieuse' => 'sometimes|nullable|boolean',
            'institution_religieuse' => 'sometimes|nullable|string|max:255',
            'niveau_juif' => 'sometimes|nullable|string|max:50',

            // ================= LANGUES =================
            'niveau_francais' => 'sometimes|nullable|string|max:50',
            'niveau_hebreu' => 'sometimes|nullable|string|max:50',
            'autres_langues' => 'sometimes|nullable|string',

            // ================= OBJECTIFS =================
            'motivation' => 'sometimes|nullable|string',
            'objectifs' => 'sometimes|nullable|string',

            // ================= CONTROLE =================
            'is_final_step' => 'sometimes|boolean',
        ];
    }
}