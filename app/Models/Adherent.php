<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;


class Adherent extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'contact',
        'pseudo',
        'password',
        'moyen_paiement',
        'preuve_paiement',
        'statut',
        'abonnement_type',
        'abonnement_expires_at',
        'is_validated'
    ];

}
