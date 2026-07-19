<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'payment_method',
        'paid_at',
        'payment_intent_id',
        'checkout_session_id',
        'charge_id',
        'currency',
        'payment_status',
        'provider',
        'provider_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_intent_id' => 'string',
            'checkout_session_id' => 'string',
            'charge_id' => 'string',
            'currency' => 'string',
            'payment_status' => 'string',
            'provider' => 'string',
            'provider_reference' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'payment_order')
            ->using(PaymentOrder::class)
            ->withPivot('amount')
            ->withTimestamps();
    }
}
