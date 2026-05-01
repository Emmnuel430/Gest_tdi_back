<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'amount',
        'currency',
        'status',
        'nom',
        'email',
        'numero',
        'type',

        "payment_step",
        "payment_index",

        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Relation polymorphique
     */
    public function transactionable()
    {
        return $this->morphTo();
    }

    /**
     * Scopes utiles (dashboard)
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}
