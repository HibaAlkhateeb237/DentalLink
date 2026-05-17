<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DepartmentWithEmployeesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employees = $this->departmentUserRoles
            ->sortByDesc(fn ($departmentUserRole) => $departmentUserRole->role->name === 'department_manager')
            ->values();

        $mapEmployee = function ($departmentUserRole): array {
            $profileImage = $departmentUserRole->user->profile_image;

            if (filled($profileImage) && ! Str::startsWith((string) $profileImage, ['http://', 'https://'])) {
                $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
                $profileImage = $publicDiskUrl !== ''
                    ? $publicDiskUrl.'/'.ltrim((string) $profileImage, '/')
                    : '/storage/'.ltrim((string) $profileImage, '/');
            }

            return [
                'id' => $departmentUserRole->user->id,
                'name' => $departmentUserRole->user->name,
                'email' => $departmentUserRole->user->email,
                'phone' => $departmentUserRole->user->phone,
                'profile_image' => $profileImage,
                'birthdate' => $departmentUserRole->user->birthdate?->format('Y-m-d'),
                'joined_at' => $departmentUserRole->user->joined_at?->format('Y-m-d'),
                'role' => [
                    'id' => $departmentUserRole->role->id,
                    'name' => $departmentUserRole->role->name,
                ],
            ];
        };

        if ($this->shouldPaginateEmployees($request)) {
            $employeesPerPage = (int) $request->query('employees_per_page', 15);
            $employeesPage = LengthAwarePaginator::resolveCurrentPage('employees_page');
            $employeesCount = $employees->count();

            $employeesPaginator = new LengthAwarePaginator(
                $employees
                    ->forPage($employeesPage, $employeesPerPage)
                    ->map($mapEmployee)
                    ->values()
                    ->all(),
                $employeesCount,
                $employeesPerPage,
                $employeesPage,
                [
                    'pageName' => 'employees_page',
                ]
            );

            $employeesPaginator->withPath($request->url());
            $employeesPaginator->appends($request->query());
            $employeesPagination = $employeesPaginator->toArray();

            $employeesPayload = [
                'data' => $employeesPagination['data'],
                'links' => [
                    'first' => $employeesPagination['first_page_url'],
                    'last' => $employeesPagination['last_page_url'],
                    'prev' => $employeesPagination['prev_page_url'],
                    'next' => $employeesPagination['next_page_url'],
                ],
                'meta' => [
                    'current_page' => $employeesPagination['current_page'],
                    'from' => $employeesPagination['from'],
                    'last_page' => $employeesPagination['last_page'],
                    'links' => $employeesPagination['links'],
                    'path' => $employeesPagination['path'],
                    'per_page' => $employeesPagination['per_page'],
                    'to' => $employeesPagination['to'],
                    'total' => $employeesPagination['total'],
                ],
            ];
        } else {
            $employeesPayload = [
                'data' => $employees
                    ->map($mapEmployee)
                    ->take(4)
                    ->values()
                    ->all(),
            ];
        }

        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_management' => $this->is_management,
            'lab' => $this->lab === null
                ? null
                : [
                    'id' => $this->lab->id,
                    'name' => $this->lab->name,
                ],
            'employees' => $employeesPayload,
            'created_at' => $this->created_at,
        ];
    }

    private function shouldPaginateEmployees(Request $request): bool
    {
        return $request->route()?->getName() === 'lab.departments.with-employees.show';
    }
}
