<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'department_id',
        'user_id',
        'approved_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(TaskWorkSession::class);
    }

    public function workedMinutes(): int
    {
        return $this->workSessions->sum(function (TaskWorkSession $session): int {
            $endTime = $session->end_time;

            if ($endTime === null && $session->status === 'active') {
                $endTime = now();
            }

            if ($endTime === null || $session->start_time === null) {
                return 0;
            }

            return max($session->start_time->diffInMinutes($endTime), 0);
        });
    }
}
