<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
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

        'is_active',
        'profile_completed'
    ];

    protected $hidden = [
        'password',
    ];

    public function profile()
    {
        return $this->hasOne(AdherentProfile::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active');
    }

    protected static function booted()
    {
        static::creating(function ($adherent) {
            if (!$adherent->pseudo) {
                $adherent->pseudo = self::generatePseudo($adherent->nom, $adherent->prenom);
            }
        });
    }

    public static function generatePseudo($nom, $prenom)
    {
        do {
            $pseudo = Str::slug($prenom . '.' . $nom) . '.' . rand(1000, 9999);
        } while (self::where('pseudo', $pseudo)->exists());

        return $pseudo;
    }

}
