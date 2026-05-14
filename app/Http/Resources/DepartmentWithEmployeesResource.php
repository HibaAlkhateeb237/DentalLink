<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentWithEmployeesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'employees' => $this->departmentUserRoles
                ->map(fn ($departmentUserRole) => [
                    'id' => $departmentUserRole->user->id,
                    'name' => $departmentUserRole->user->name,
                    'email' => $departmentUserRole->user->email,
                    'phone' => $departmentUserRole->user->phone,
                    'role' => [
                        'id' => $departmentUserRole->role->id,
                        'name' => $departmentUserRole->role->name,
                    ],
                ])
                ->values(),
            'created_at' => $this->created_at,
        ];
    }
}
