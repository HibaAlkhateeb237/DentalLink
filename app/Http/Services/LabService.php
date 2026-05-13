<?php

namespace App\Http\Services;

use App\Http\Repositories\LabRepository;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LabService
{
    public function __construct(
        protected LabRepository $labRepository
    ) {}

    public function getLabById(int $labId): Lab
    {
        return $this->labRepository->getWithReviewStats($labId);
    }

    public function getLabs(int $perPage = 15): LengthAwarePaginator
    {
        $labs = $this->labRepository->paginateActive($perPage);

        $labIds = collect($labs->items())
            ->pluck('id')
            ->filter()
            ->values();

        $managersByLabId = $this->resolveManagersByLabIds($labIds->all());

        return $labs->through(function (Lab $lab) use ($managersByLabId): array {
            return $this->buildLabPayload($lab, $managersByLabId->get($lab->id));
        });
    }

    public function searchLabs(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->searchPaginated($search, $perPage);
    }

    public function getInactiveLabs(int $perPage = 15): LengthAwarePaginator
    {
        $labs = $this->labRepository->getInactiveLabs($perPage);

        $labIds = collect($labs->items())
            ->pluck('id')
            ->filter()
            ->values();

        $managersByLabId = $this->resolveManagersByLabIds($labIds->all());

        return $labs->through(function (Lab $lab) use ($managersByLabId): array {
            return $this->buildLabPayload($lab, $managersByLabId->get($lab->id));
        });
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

        $ratingToShow = (float) $averageRating > 0 ? (float) $averageRating : (float) $lab->rating;

        return [
            'id' => $lab->id,
            'name' => $lab->name,
            'license_number' => $lab->license_number,
            'phone' => $lab->phone,
            'address' => $lab->address,
            'latitude' => $lab->latitude,
            'longitude' => $lab->longitude,
            'rating' => number_format($ratingToShow, 2, '.', ''),
        ];
    }

    public function getAdminLabs(int $perPage = 15): LengthAwarePaginator
    {
        $labs = $this->labRepository->paginateAll($perPage);

        $labIds = collect($labs->items())
            ->pluck('id')
            ->filter()
            ->values();

        $managersByLabId = $this->resolveManagersByLabIds($labIds->all());

        return $labs->through(function (Lab $lab) use ($managersByLabId): array {
            return $this->buildLabPayload($lab, $managersByLabId->get($lab->id));
        });
    }

    /**
     * @param  array{lab_name:string,manager_name:string,phone:string,address:string,latitude:numeric-string|int|float,longitude:numeric-string|int|float,email:string,password:string}  $validated
     * @return array{lab:array<string,mixed>}
     */
    public function createLabWithManager(array $validated): array
    {
        return DB::transaction(function () use ($validated): array {
            $lab = Lab::query()->create([
                'name' => $validated['lab_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'rating' => 0,
            ]);

            $lab->license_number = $this->generateLabLicenseNumber($lab->id);

            // Handle photo upload
            if (isset($validated['photo']) && $validated['photo'] !== null) {
                $photoPath = $this->storeLabPhoto($validated['photo'], $lab->id);
                $lab->photo = $photoPath;
            }

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
     * @param  array{lab_name:string,phone:string,address:string,latitude:numeric-string|int|float,longitude:numeric-string|int|float,manager_name?:string|null,email?:string|null,password?:string|null}  $validated
     * @return array{lab:array<string,mixed>}
     */
    public function updateLabWithManager(Lab $lab, array $validated): array
    {
        return DB::transaction(function () use ($lab, $validated): array {
            $lab->fill([
                'name' => $validated['lab_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ]);

            // Handle photo upload
            if (isset($validated['photo']) && $validated['photo'] !== null) {
                // Delete old photo if exists
                if ($lab->photo) {
                    Storage::disk('public')->delete($lab->photo);
                }
                $photoPath = $this->storeLabPhoto($validated['photo'], $lab->id);
                $lab->photo = $photoPath;
            }

            $lab->save();

            $manager = User::query()
                ->select(['users.id', 'users.name', 'users.email', 'users.phone'])
                ->join('department_user_roles', 'department_user_roles.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
                ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
                ->where('departments.lab_id', $lab->id)
                ->where('roles.name', 'lab_manager')
                ->where('roles.guard_name', 'sanctum')
                ->orderBy('users.id')
                ->first();

            if ($manager !== null) {
                $managerUpdates = [
                    'phone' => $validated['phone'],
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

            ];
        });
    }

    public function deleteLab(Lab $lab): void
    {
        DB::transaction(function () use ($lab): void {
            $departmentIds = Department::query()
                ->where('lab_id', $lab->id)
                ->pluck('id');

            $managerIds = User::query()
                ->join('department_user_roles', 'department_user_roles.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
                ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
                ->where('departments.lab_id', $lab->id)
                ->where('roles.name', 'lab_manager')
                ->where('roles.guard_name', 'sanctum')
                ->pluck('users.id');

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

            if ($departmentIds->isNotEmpty()) {
                Task::query()
                    ->whereIn('department_id', $departmentIds)
                    ->delete();

                Department::query()
                    ->whereIn('id', $departmentIds)
                    ->delete();
            }

            $lab->delete();
        });
    }

    public function getAdminLabDetails(Lab $lab): array
    {
        $manager = $this->resolveManagersByLabIds([$lab->id])->get($lab->id);

        return $this->buildLabPayload($lab, $manager);
    }

    /**
     * @param  array<int, int>  $labIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveManagersByLabIds(array $labIds): \Illuminate\Support\Collection
    {
        if ($labIds === []) {
            return collect();
        }

        $departmentManagersByLabId = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                DB::raw('departments.lab_id as lab_id'),
            ])
            ->join('department_user_roles', 'department_user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->whereIn('departments.lab_id', $labIds)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->orderBy('users.id')
            ->get()
            ->unique('lab_id')
            ->keyBy('lab_id');

        return $departmentManagersByLabId;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildLabPayload(Lab $lab, ?User $manager): array
    {
        $photoUrl = null;
        if ($lab->photo) {
            if (str_starts_with($lab->photo, 'http://') || str_starts_with($lab->photo, 'https://')) {
                $photoUrl = $lab->photo;
            } else {
                try {
                    $photoUrl = url(Storage::url($lab->photo));
                } catch (\Throwable $e) {
                    $photoUrl = null;
                }
            }
        }

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
            'photo' => $photoUrl,
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

    private function storeLabPhoto($photoFile, int $labId): string
    {
        $extension = $photoFile->getClientOriginalExtension();
        $filename = "lab{$labId}.{$extension}";
        $path = $photoFile->storeAs('labs', $filename, 'public');

        return $path;
    }

    private function generateLabLicenseNumber(int $labId): string
    {
        return 'LAB-' . now()->format('Ymd') . '-' . str_pad((string) $labId, 6, '0', STR_PAD_LEFT);
    }
}
