<?php

namespace App\Services;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\DepartmentUserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DentalCompensationTypeService
{
    public function search(?string $q, User $user): Builder
    {
        $managerLabId = $this->resolveManagerLabId($user);
        $query = DentalCompensationType::query()->where('lab_id', $managerLabId);
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%")
                    ->orWhere('code', 'like', "%$q%")
                    ->orWhere('category', 'like', "%$q%");
            });
        }

        return $query->orderByDesc('id');
    }

    public function create(array $data, User $user): DentalCompensationType
    {
        $managerLabId = $this->resolveManagerLabId($user);

        try {
            return DB::transaction(function () use ($data, $managerLabId) {
                $comp = DentalCompensationType::create([
                    'lab_id' => $managerLabId,
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
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'dental_compensation_types_lab_id_name_unique')) {
                throw ValidationException::withMessages([
                    'name' => [__('messages.duplicate_compensation_name', ['name' => $data['name']])],
                ]);
            }
            throw $e;
        }
    }

    public function update(DentalCompensationType $comp, array $data, User $user): DentalCompensationType
    {
        return DB::transaction(function () use ($comp, $data) {
            $comp->update(Arr::only($data, ['name', 'description', 'category', 'code']));

            if (isset($data['price'])) {
                // deactivate existing prices for this type
                DentalCompensationTypePrice::where('dental_compensation_type_id', $comp->id)
                    ->update(['is_active' => false]);

                $effectiveDate = now()->toDateString();

                // reuse existing price row if there's already one for today
                $existing = DentalCompensationTypePrice::where('dental_compensation_type_id', $comp->id)
                    ->whereDate('effective_from', $effectiveDate)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'base_price' => $data['price'],
                        'is_active' => true,
                    ]);
                } else {
                    DentalCompensationTypePrice::create([
                        'dental_compensation_type_id' => $comp->id,
                        'base_price' => $data['price'],
                        'effective_from' => $effectiveDate,
                        'is_active' => true,
                    ]);
                }
            }

            return $comp->fresh();
        });
    }

    public function delete(DentalCompensationType $comp, User $user): void
    {
        DB::transaction(fn() => $comp->delete());
    }

    private function resolveManagerLabId(User $user): int
    {
        $managerLabId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        if ($managerLabId === null) {
            throw ValidationException::withMessages([
                'lab_id' => [__('messages.not_found')],
            ]);
        }

        return (int) $managerLabId;
    }
}
