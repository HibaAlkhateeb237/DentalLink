<?php

namespace App\Models;

use App\Support\OrderStatus;
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
        'serial_number',
        'patient_name',
        'received_at',
        'delivered_at',
        'expected_delivery_at',
        'qr_code',
        'qr_image_path',
        'priority',
        'status',
        'order_type',
        'case_type',
        'notes',
        'price',
        'remaining_amount',

        'requires_resubmission',
        'resubmission_reason',
        'resubmission_requested_at',
        'resubmission_requested_by',

        'tooth_shade_id',
        'dental_compensation_type_price_id',
        'is_in_delivery',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'remaining_amount' => 'decimal:2',

            'requires_resubmission' => 'boolean',
            'resubmission_requested_at' => 'datetime',
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'is_in_delivery' => 'boolean',

            'qr_image_path' => 'string',

        ];
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function toothShade(): BelongsTo
    {
        return $this->belongsTo(ToothShade::class);
    }

    public function dentalCompensationTypePrice(): BelongsTo
    {
        return $this->belongsTo(DentalCompensationTypePrice::class);
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

    public function portfolioCase(): HasOne
    {
        return $this->hasOne(PortfolioCase::class);
    }

    public function currentTask(): ?Task
    {
        if (! $this->relationLoaded('tasks')) {
            return null;
        }

        $processingStatuses = [OrderStatus::IN_PROGRESS, OrderStatus::TRY_ON, OrderStatus::RESEND_WRONG_IMPRESSION];

        if (! in_array($this->status, $processingStatuses, true)) {
            return null;
        }

        $tasks = $this->tasks;

        return $tasks
            ->filter(fn (Task $task): bool => in_array($task->status, ['assigned', 'in_progress'], true))
            ->sortByDesc('id')
            ->first();
    }

    public function currentDepartment(): ?Department
    {
        return $this->currentTask()?->department;
    }
}
