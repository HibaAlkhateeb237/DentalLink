<?php

namespace App\Http\Services;

use App\Http\Repositories\LabRepository;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LabService
{
    public function __construct(
        protected LabRepository $labRepository
    ) {}

    public function getLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateAll($perPage);
    }

    public function searchLabs(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->searchPaginated($search, $perPage);
    }

    public function getTopRatedLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateTopRated($perPage);
    }

    public function getNearbyLabs(int $doctorId, int $perPage = 15): LengthAwarePaginator
    {
        $doctor = User::query()
            ->select(['id', 'location_lat', 'location_lng'])
            ->findOrFail($doctorId);

        return $this->labRepository->paginateNearby(
            (float) $doctor->location_lat,
            (float) $doctor->location_lng,
            $perPage
        );
    }

    public function getSuggestedLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateSuggested($perPage);
    }

    public function getMostOrderedLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateMostOrdered($perPage);
    }

    public function getLabDetails(Lab $lab): array
    {
        return $lab->only([
            'id',
            'license_number',
            'name',
            'phone',
            'address',
            'latitude',
            'longitude',
            'rating',
        ]);
    }

    public function getAdminLabs(int $perPage = 15): LengthAwarePaginator
    {
        $labs = $this->labRepository->paginateAll($perPage);

        $labIds = $labs->getCollection()
            ->pluck('id')
            ->filter()
            ->values();

        $managersByLabId = User::query()
            ->select(['id', 'name', 'email', 'phone', 'lab_id'])
            ->whereIn('lab_id', $labIds)
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
            })
            ->orderBy('id')
            ->get()
            ->keyBy('lab_id');

        $labs->setCollection(
            $labs->getCollection()->map(function (Lab $lab) use ($managersByLabId): array {
                return $this->buildLabPayload($lab, $managersByLabId->get($lab->id));
            })
        );

        return $labs;
    }

    /**
     * @param  array{lab_name:string,manager_name:string,phone:string,location:string,latitude:numeric-string|int|float,longitude:numeric-string|int|float,email:string,password:string}  $validated
     * @return array{lab:array<string,mixed>,manager:array<string,mixed>|null}
     */
    public function createLabWithManager(array $validated): array
    {
        return DB::transaction(function () use ($validated): array {
            $lab = Lab::query()->create([
                'name' => $validated['lab_name'],
                'phone' => $validated['phone'],
                'address' => $validated['location'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'rating' => 0,
            ]);

            $lab->license_number = $this->generateLabLicenseNumber($lab->id);
            $lab->save();

            $managementDepartment = Department::query()->create([
                'lab_id' => $lab->id,
                'name' => 'Management',
                'is_management' => true,
            ]);

            $manager = User::query()->create([
                'name' => $validated['manager_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'lab_id' => $lab->id,
                'password' => $validated['password'],
            ]);

            $labManagerRole = Role::query()
                ->where('name', 'lab_manager')
                ->where('guard_name', 'sanctum')
                ->firstOrFail();

            DepartmentUserRole::query()->firstOrCreate([
                'user_id' => $manager->id,
                'role_id' => $labManagerRole->id,
                'department_id' => $managementDepartment->id,
            ]);

            $manager->roles()->syncWithoutDetaching([$labManagerRole->id]);

            return [
                'lab' => $this->buildLabPayload($lab, $manager),
            ];
        });
    }

    /**
     * @param  array{lab_name:string,phone:string,location:string,latitude:numeric-string|int|float,longitude:numeric-string|int|float,manager_name?:string|null,email?:string|null,password?:string|null}  $validated
     * @return array{lab:array<string,mixed>,manager:array<string,mixed>|null}
     */
    public function updateLabWithManager(Lab $lab, array $validated): array
    {
        return DB::transaction(function () use ($lab, $validated): array {
            $lab->fill([
                'name' => $validated['lab_name'],
                'phone' => $validated['phone'],
                'address' => $validated['location'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ]);
            $lab->save();

            $manager = User::query()
                ->where('lab_id', $lab->id)
                ->whereHas('roles', function ($query): void {
                    $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
                })
                ->orderBy('id')
                ->first();

            if ($manager !== null) {
                $managerUpdates = [
                    'phone' => $validated['phone'],
                    'lab_id' => $lab->id,
                ];

                if (isset($validated['manager_name'])) {
                    $managerUpdates['name'] = $validated['manager_name'];
                }

                if (isset($validated['email'])) {
                    $managerUpdates['email'] = $validated['email'];
                }

                if (isset($validated['password']) && $validated['password'] !== '') {
                    $managerUpdates['password'] = $validated['password'];
                }

                $manager->fill($managerUpdates);
                $manager->save();
            }

            return [
                'lab' => $this->buildLabPayload($lab->fresh(), $manager?->fresh()),
                'manager' => $this->buildManagerPayload($manager?->fresh()),
            ];
        });
    }

    public function deleteLab(Lab $lab): void
    {
        DB::transaction(function () use ($lab): void {
            $managerIds = User::query()
                ->where('lab_id', $lab->id)
                ->whereHas('roles', function ($query): void {
                    $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
                })
                ->pluck('id');

            $labManagerRoleId = Role::query()
                ->where('name', 'lab_manager')
                ->where('guard_name', 'sanctum')
                ->value('id');

            if ($managerIds->isNotEmpty() && $labManagerRoleId !== null) {
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('role_id', $labManagerRoleId)
                    ->whereIn('model_id', $managerIds)
                    ->delete();
            }

            User::query()
                ->where('lab_id', $lab->id)
                ->update(['lab_id' => null]);

            $lab->delete();
        });
    }

    public function getAdminLabDetails(Lab $lab): array
    {
        $manager = User::query()
            ->select(['id', 'name', 'email', 'phone', 'lab_id'])
            ->where('lab_id', $lab->id)
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
            })
            ->orderBy('id')
            ->first();

        return $this->buildLabPayload($lab, $manager);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildLabPayload(Lab $lab, ?User $manager): array
    {
        return [
            'id' => $lab->id,
            'lab_name' => $lab->name,
            'license_number' => $lab->license_number,
            'location' => $lab->address,
            'latitude' => $lab->latitude,
            'longitude' => $lab->longitude,
            'name' => $lab->name,
            'phone' => $lab->phone,
            'address' => $lab->address,
            'rating' => $lab->rating,
            'created_at' => $lab->created_at,
            'updated_at' => $lab->updated_at,
            'manager' => $this->buildManagerPayload($manager),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildManagerPayload(?User $manager): ?array
    {
        if ($manager === null) {
            return null;
        }

        return [
            'id' => $manager->id,
            'name' => $manager->name,
            'email' => $manager->email,
            'phone' => $manager->phone,
        ];
    }

    private function generateLabLicenseNumber(int $labId): string
    {
        return 'LAB-' . now()->format('Ymd') . '-' . str_pad((string) $labId, 6, '0', STR_PAD_LEFT);
    }
}
