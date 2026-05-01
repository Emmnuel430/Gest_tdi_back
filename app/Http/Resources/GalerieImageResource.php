<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GalerieImageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'media' => [
                'id' => $this->media->id ?? null,
                'url' => $this->media->url ?? null,
            ],
        ];
    }
}
