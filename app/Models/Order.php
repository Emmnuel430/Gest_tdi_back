<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
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
        'metadata',
        'commune',
        'total_items',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
