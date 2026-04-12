<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalCompensationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'name',
        'reference_price',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'reference_price' => 'decimal:2',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
