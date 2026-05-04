<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabPricingSetting extends Model
{
    protected $fillable = [
        'lab_id',
        'currency',
        'effective_from',
        'implant_addon',
        'long_bridge_or_high_addon',
        'lisi_connect_etching_addon',
        'intraoral_print_fee',
        'vip_urgent_multiplier',
        'student_discount_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lab_id' => 'integer',
            'effective_from' => 'date',
            'implant_addon' => 'decimal:2',
            'long_bridge_or_high_addon' => 'decimal:2',
            'lisi_connect_etching_addon' => 'decimal:2',
            'intraoral_print_fee' => 'decimal:2',
            'vip_urgent_multiplier' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
