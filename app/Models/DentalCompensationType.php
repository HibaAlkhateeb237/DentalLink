<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DentalCompensationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'code',
        'name',
        'category',
        'description',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(DentalCompensationTypePrice::class);
    }
}
