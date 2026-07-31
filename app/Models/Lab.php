<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lab extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'license_number',
        'phone',
        'address',
        'latitude',
        'longitude',
        'is_active',
        'photo',

        'stripe_account_id',

        'normal_delivery_days',
        'urgent_delivery_days',

        'package_id',

    ];

    protected function casts(): array
    {
        return [
            /*  'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',*/

            'latitude' => 'float',
            'longitude' => 'float',

            'is_active' => 'boolean',
            'normal_delivery_days' => 'integer',
            'urgent_delivery_days' => 'integer',
        ];
    }

    public function deliveryDaysForPriority(string $priority): int
    {
        if ($priority === 'urgent') {
            return (int) ($this->urgent_delivery_days ?? 1);
        }

        return (int) ($this->normal_delivery_days ?? 3);
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

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function packageHistories(): HasMany
    {
        return $this->hasMany(LabPackageHistory::class);
    }
}
