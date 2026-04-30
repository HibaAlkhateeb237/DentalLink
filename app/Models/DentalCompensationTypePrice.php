<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalCompensationTypePrice extends Model
{
    protected $fillable = [
        'dental_compensation_type_id',
        'base_price',
        'effective_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'dental_compensation_type_id' => 'integer',
            'base_price' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function dentalCompensationType(): BelongsTo
    {
        return $this->belongsTo(DentalCompensationType::class);
    }
}
