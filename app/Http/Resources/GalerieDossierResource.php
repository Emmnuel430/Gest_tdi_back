<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GalerieDossierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'images_count' => $this->images_count,
            'images' => GalerieImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
