<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdherentResource extends JsonResource
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
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'contact' => $this->contact,
            'pseudo' => $this->pseudo,
            'profile_completed' => $this->profile_completed ? "true" : "false",

            // Charge le profil uniquement s'il est présent dans la requête SQL
            'profile' => $this->whenLoaded('profile'),

            // On appelle la ressource Subscription si elle existe
            'subscription' => new SubscriptionResource($this->whenLoaded('activeSubscription')),
        ];
    }
}
