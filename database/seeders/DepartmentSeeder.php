<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labs = Lab::query()->select(['id', 'name', 'phone', 'address', 'latitude', 'longitude'])->get();

        if ($labs->isEmpty()) {
            return;
        }

        $departmentNames = [
            'reception',
            'plaster',
            'margins',
            'design',
            'ceramics',
            'polishing',
            'Management',
        ];

        $now = now();
        $rows = [];

        foreach ($labs as $lab) {
            foreach ($departmentNames as $departmentName) {
                $rows[] = [
                    'lab_id' => $lab->id,
                    'name' => $departmentName,
                    'description' => null,
                    'is_management' => $departmentName === 'Management',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        Department::query()->upsert(
            $rows,
            ['lab_id', 'name'],
            ['description', 'is_management', 'updated_at']
        );

        $labManagerRole = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->first();

        if ($labManagerRole === null) {
            return;
        }

        $managementDepartmentsByLabId = Department::query()
            ->select(['id', 'lab_id'])
            ->where('name', 'Management')
            ->where('is_management', true)
            ->whereIn('lab_id', $labs->pluck('id'))
            ->get()
            ->keyBy('lab_id');

        foreach ($labs as $lab) {
            $managementDepartment = $managementDepartmentsByLabId->get($lab->id);

            if ($managementDepartment === null) {
                continue;
            }

            $manager = User::query()->updateOrCreate(
                ['email' => 'manager.lab'.$lab->id.'@dentalink.local'],
                [
                    'name' => $lab->name.' Manager',
                    'phone' => $lab->phone,
                    'location' => $lab->address,
                    'location_lat' => $lab->latitude,
                    'location_lng' => $lab->longitude,
                    'password' => 'Password@123',
                ]
            );

            $manager->roles()->syncWithoutDetaching([$labManagerRole->id]);

            DepartmentUserRole::query()->updateOrCreate(
                [
                    'user_id' => $manager->id,
                    'role_id' => $labManagerRole->id,
                    'department_id' => $managementDepartment->id,
                ],
                []
            );
        }
    }
}
