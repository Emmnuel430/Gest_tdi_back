<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tsedaka extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'nom',
        'prenom',
        'email',
        'montant',
        'anonymous',
        'message',
    ];

    protected $casts = [
        'anonymous' => 'boolean',
    ];

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}
