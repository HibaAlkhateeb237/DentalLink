<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lab_id',
        'qr_code',
        'priority',
        'status',
        'order_type',
        'notes',
        'price',
        'remaining_amount',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function orderTeeth(): HasMany
    {
        return $this->hasMany(OrderTooth::class);
    }

    public function orderFiles(): HasMany
    {
        return $this->hasMany(OrderFile::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function deliveryTasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_order')
            ->using(PaymentOrder::class)
            ->withPivot('amount')
            ->withTimestamps();
    }
}
