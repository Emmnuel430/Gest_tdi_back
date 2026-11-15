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
        'status',
        'resource_delivery_status',
        'resource_recipient_email',
        'resource_sent_at',
        'delivery_attempts',
        'delivery_error',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resource_sent_at' => 'datetime',
        'delivery_attempts' => 'integer',
    ];

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
