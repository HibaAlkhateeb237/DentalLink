<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public function search(?string $q, ?float $minPrice = null, ?float $maxPrice = null, ?int $minDuration = null, ?int $maxDuration = null, ?User $user = null): Builder
    {
        $query = Package::query();

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%");
            });
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($minDuration !== null) {
            $query->where('duration_days', '>=', $minDuration);
        }

        if ($maxDuration !== null) {
            $query->where('duration_days', '<=', $maxDuration);
        }

        if ($user && ! $user->hasRole('system_admin')) {
            $query->where('is_active', true);
        }

        return $query->orderByDesc('id');
    }

    public function create(array $data, User $user): Package
    {
        return DB::transaction(function () use ($data) {
            return Package::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'duration_days' => $data['duration_days'],
                'price' => $data['price'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function update(Package $package, array $data, User $user): Package
    {
        return DB::transaction(function () use ($package, $data) {
            $package->update([
                'name' => $data['name'] ?? $package->name,
                'description' => Arr::get($data, 'description', $package->description),
                'duration_days' => $data['duration_days'] ?? $package->duration_days,
                'price' => $data['price'] ?? $package->price,
                'is_active' => $data['is_active'] ?? $package->is_active,
            ]);

            return $package->fresh();
        });
    }

    public function delete(Package $package, User $user): void
    {
        DB::transaction(fn () => $package->delete());
    }
}
