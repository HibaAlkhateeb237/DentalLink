<?php

namespace App\Models;

use App\Support\DeliveryTrackStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'delivery_task_id',
        'delivery_person_id',
        'latitude',
        'longitude',
        'status',
        'location_recorded_at',
    ];

    protected $attributes = [
        'status' => DeliveryTrackStatus::PENDING,
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_recorded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryTask(): BelongsTo
    {
        return $this->belongsTo(DeliveryTask::class);
    }

    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_person_id');
    }
}
