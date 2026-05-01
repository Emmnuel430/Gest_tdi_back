<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdherentProfile extends Model
{
    use HasFactory;
    protected $fillable = [
        'adherent_id',

        'adresse',
        'date_naissance',
        'situation_matrimoniale',
        'nombre_enfants',
        'profession',

        'telephone_whatsapp',
        'telephone_secondaire',

        'urgence_nom',
        'urgence_numero',
        'urgence_lien',

        'niveau_etudes',
        'dernier_diplome',

        'etude_religieuse',
        'institution_religieuse',
        'niveau_juif',

        'niveau_francais',
        'niveau_hebreu',
        'autres_langues',

        'motivation',
        'objectifs',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'etude_religieuse' => 'boolean',
        'nombre_enfants' => 'integer',
    ];

    public function adherent()
    {
        return $this->belongsTo(Adherent::class);
    }
}
