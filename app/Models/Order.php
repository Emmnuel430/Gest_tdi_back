<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'nom',
        'email',
        'numero',
        'metadata',
        'commune',
        'total_items',
        'status'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
