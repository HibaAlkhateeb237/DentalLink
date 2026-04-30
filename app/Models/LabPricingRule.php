<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabPricingRule extends Model
{
    protected $fillable = [
        'lab_id',
        'code',
        'name',
        'effective_from',
        'applies_to',
        'kind',
        'value',
        'per_unit',
        'condition',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lab_id' => 'integer',
            'effective_from' => 'date',
            'value' => 'decimal:4',
            'per_unit' => 'boolean',
            'condition' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
