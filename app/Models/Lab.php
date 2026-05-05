<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lab extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'license_number',
        'phone',
        'address',
        'latitude',
        'longitude',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating' => 'decimal:2',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function dentalCompensationTypes(): HasMany
    {
        return $this->hasMany(DentalCompensationType::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
