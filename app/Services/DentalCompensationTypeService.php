<?php

namespace App\Services;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\User;

class DentalCompensationTypeService
{
    public function search(?string $q, User $user): Builder
    {
        $query = DentalCompensationType::query()->where('lab_id', $user->lab_id);
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%")
                    ->orWhere('code', 'like', "%$q%")
                    ->orWhere('category', 'like', "%$q%")
                ;
            });
        }
        return $query->orderByDesc('id');
    }

    public function create(array $data, User $user): DentalCompensationType
    {
        return DB::transaction(function () use ($data, $user) {
            $comp = DentalCompensationType::create([
                'lab_id' => $user->lab_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'code' => Arr::get($data, 'code', Str::uuid()),
                'category' => Arr::get($data, 'category'),
            ]);
            DentalCompensationTypePrice::create([
                'dental_compensation_type_id' => $comp->id,
                'base_price' => $data['price'],
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]);
            return $comp->fresh();
        });
    }

    public function update(DentalCompensationType $comp, array $data, User $user): DentalCompensationType
    {
        return DB::transaction(function () use ($comp, $data) {
            $comp->update(Arr::only($data, ['name', 'description', 'category', 'code']));
            if (isset($data['price'])) {
                DentalCompensationTypePrice::where('dental_compensation_type_id', $comp->id)
                    ->update(['is_active' => false]);
                DentalCompensationTypePrice::create([
                    'dental_compensation_type_id' => $comp->id,
                    'base_price' => $data['price'],
                    'effective_from' => now()->toDateString(),
                    'is_active' => true,
                ]);
            }
            return $comp->fresh();
        });
    }

    public function delete(DentalCompensationType $comp, User $user): void
    {
        DB::transaction(fn() => $comp->delete());
    }
}
