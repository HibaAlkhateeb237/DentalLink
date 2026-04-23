<?php

namespace App\Http\Services;

use App\Http\Repositories\LabRepository;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function getTopRatedLabs(?int $limit = null): Collection
    {
        return $this->labRepository->getTopRated($limit);
    }

    public function getNearbyLabs(int $doctorId, ?int $limit = null): Collection
    {
        $doctor = User::query()
            ->select(['id', 'location_lat', 'location_lng'])
            ->findOrFail($doctorId);

        if ($doctor->location_lat === null || $doctor->location_lng === null) {
            throw ValidationException::withMessages([
                'doctor_id' => ['The selected doctor must have a location.'],
            ]);
        }

        return $this->labRepository->getNearby(
            (float) $doctor->location_lat,
            (float) $doctor->location_lng,
            $limit
        );
    }

    public function getSuggestedLabs(?int $limit = null): Collection
    {
        return $this->labRepository->getSuggested($limit);
    }

    public function getMostOrderedLabs(?int $limit = null): Collection
    {
        return $this->labRepository->getMostOrdered($limit);
    }

    public function getLabDetails(Lab $lab): array
    {
        $averageRating = Lab::query()
            ->whereKey($lab->id)
            ->selectRaw('COALESCE((SELECT AVG(reviews.rating) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id), 0) as rating')
            ->value('rating');

        return [
            'id' => $lab->id,
            'name' => $lab->name,
            'phone' => $lab->phone,
            'address' => $lab->address,
            'latitude' => $lab->latitude,
            'longitude' => $lab->longitude,
            'rating' => number_format((float) $averageRating, 2, '.', ''),
        ];
    }

    public function getAdminLabs(int $perPage = 15): LengthAwarePaginator
    {
        $labs = $this->labRepository->paginateAll($perPage);

        $labNames = $labs->getCollection()
            ->pluck('name')
            ->filter()
            ->values();

        $managersByLabName = User::query()
            ->select(['id', 'name', 'email', 'phone', 'lab_name'])
            ->whereIn('lab_name', $labNames)
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
            })
            ->orderBy('id')
            ->get()
            ->keyBy('lab_name');

        $labs->setCollection(
            $labs->getCollection()->map(function (Lab $lab) use ($managersByLabName): array {
                return $this->buildLabPayload($lab, $managersByLabName->get($lab->name));
            })
        );

        return $labs;
    }

    /**
     * @param  array{lab_name:string,manager_name:string,phone:string,location:string,email:string,password:string}  $validated
     * @return array{lab:array<string,mixed>,manager:array<string,mixed>|null}
     */
    public function createLabWithManager(array $validated): array
    {
        return DB::transaction(function () use ($validated): array {
            $lab = Lab::query()->create([
                'name' => $validated['lab_name'],
                'phone' => $validated['phone'],
                'address' => $validated['location'],
                'latitude' => 0,
                'longitude' => 0,
                'rating' => 0,
            ]);

            $manager = User::query()->create([
                'name' => $validated['manager_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'lab_name' => $lab->name,
                'password' => $validated['password'],
            ]);

            $labManagerRoleId = Role::query()
                ->where('name', 'lab_manager')
                ->where('guard_name', 'sanctum')
                ->value('id');

            if ($labManagerRoleId !== null) {
                $manager->roles()->syncWithoutDetaching([$labManagerRoleId]);
            }

            return [
                'lab' => $this->buildLabPayload($lab, $manager),
                'manager' => $this->buildManagerPayload($manager),
            ];
        });
    }

    /**
     * @param  array{lab_name:string,phone:string,location:string,manager_name?:string|null,email?:string|null,password?:string|null}  $validated
     * @return array{lab:array<string,mixed>,manager:array<string,mixed>|null}
     */
    public function updateLabWithManager(Lab $lab, array $validated): array
    {
        return DB::transaction(function () use ($lab, $validated): array {
            $currentLabName = $lab->name;

            $lab->fill([
                'name' => $validated['lab_name'],
                'phone' => $validated['phone'],
                'address' => $validated['location'],
            ]);
            $lab->save();

            $manager = User::query()
                ->where('lab_name', $currentLabName)
                ->whereHas('roles', function ($query): void {
                    $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
                })
                ->orderBy('id')
                ->first();

            if ($manager !== null) {
                $managerUpdates = [
                    'phone' => $validated['phone'],
                    'lab_name' => $lab->name,
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

            $otherUsersInLab = User::query()->where('lab_name', $currentLabName);

            if ($manager !== null) {
                $otherUsersInLab->where('id', '!=', $manager->id);
            }

            $otherUsersInLab->update(['lab_name' => $lab->name]);

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
                ->where('lab_name', $lab->name)
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
                ->where('lab_name', $lab->name)
                ->update(['lab_name' => null]);

            $lab->delete();
        });
    }

    public function getAdminLabDetails(Lab $lab): array
    {
        $manager = User::query()
            ->select(['id', 'name', 'email', 'phone', 'lab_name'])
            ->where('lab_name', $lab->name)
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
            'location' => $lab->address,
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
}
