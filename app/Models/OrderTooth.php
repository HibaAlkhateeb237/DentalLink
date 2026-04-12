<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTooth extends Model
{
    use HasFactory;

    protected $table = 'order_teeth';

    protected $fillable = [
        'order_id',
        'tooth_number',
        'tooth_type',
        'tooth_color',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
