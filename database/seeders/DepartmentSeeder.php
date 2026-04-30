<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Lab;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labs = Lab::query()->select('id')->get();

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
        ];

        $now = now();
        $rows = [];

        foreach ($labs as $lab) {
            foreach ($departmentNames as $departmentName) {
                $rows[] = [
                    'lab_id' => $lab->id,
                    'name' => $departmentName,
                    'description' => null,
                    'is_management' => false,
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
    }
}
