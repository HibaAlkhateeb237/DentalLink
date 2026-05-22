<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $user = $resource instanceof User ? $resource : $this->user;

        if ($user instanceof User) {
            $user->loadMissing('departmentUserRoles.department.lab', 'departmentUserRoles.role');
        }

        /** @var Collection<int, mixed> $assignments */
        $assignments = $resource instanceof User
            ? $resource->departmentUserRoles
            : collect([$this]);

        $departments = $assignments
            ->map(static function ($assignment): ?array {
                $department = $assignment->department ?? null;

                if ($department === null) {
                    return null;
                }

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'lab' => $department->lab === null
                        ? null
                        : [
                            'id' => $department->lab->id,
                            'name' => $department->lab->name,
                        ],
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        $primaryDepartment = $departments->first();
        $role = $assignments->first()?->role;

        $profileImage = $user?->profile_image;

        if (filled($profileImage) && ! Str::startsWith((string) $profileImage, ['http://', 'https://'])) {
            $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
            $profileImage = $publicDiskUrl !== ''
                ? $publicDiskUrl.'/'.ltrim((string) $profileImage, '/')
                : '/storage/'.ltrim((string) $profileImage, '/');
        }

        $payload = [
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'profile_image' => $profileImage,
            'birthdate' => $user?->birthdate?->format('Y-m-d'),
            'joined_at' => $user?->joined_at?->format('Y-m-d'),
            'departments' => $departments->all(),
            'departments_ids' => $departments->pluck('id')->all(),
            'role' => $role === null
                ? null
                : [
                    'id' => $role->id,
                    'name' => $role->name,
                ],
        ];

        if ($role?->name !== 'department_manager') {
            $payload['department'] = $primaryDepartment;
        }

        return $payload;
    }
}
