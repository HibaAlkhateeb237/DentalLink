<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_assigns_receptionists_to_first_lab(): void
    {
        $this->seed(DemoDataSeeder::class);

        $firstLab = Lab::query()->orderBy('id')->first();
        $this->assertNotNull($firstLab);

        $receptionistRoleId = Role::query()
            ->where('name', 'receptionist')
            ->where('guard_name', 'sanctum')
            ->value('id');
        $this->assertNotNull($receptionistRoleId);

        $managementDepartment = Department::query()
            ->where('lab_id', $firstLab->id)
            ->where('is_management', true)
            ->first();
        $this->assertNotNull($managementDepartment);

        $receptionists = User::query()
            ->whereHas('roles', function ($query) use ($receptionistRoleId): void {
                $query->where('roles.id', $receptionistRoleId);
            })
            ->get();

        $this->assertSame(3, $receptionists->count());
        $this->assertTrue($receptionists->every(function (User $user) use ($firstLab, $managementDepartment, $receptionistRoleId): bool {
            return $user->lab_name === $firstLab->name
                && $user->departmentUserRoles()
                ->where('role_id', $receptionistRoleId)
                ->where('department_id', $managementDepartment->id)
                ->exists();
        }));
    }

    public function test_demo_data_seeder_assigns_lab_managers_to_each_lab(): void
    {
        $this->seed(DemoDataSeeder::class);

        $labManagerRoleId = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');
        $this->assertNotNull($labManagerRoleId);

        $labs = Lab::query()->orderBy('id')->get();
        $this->assertNotEmpty($labs);

        foreach ($labs as $lab) {
            $managementDepartment = Department::query()
                ->where('lab_id', $lab->id)
                ->where('is_management', true)
                ->first();
            $this->assertNotNull($managementDepartment);

            $this->assertTrue(
                $managementDepartment->departmentUserRoles()
                    ->where('role_id', $labManagerRoleId)
                    ->exists()
            );
        }
    }
}
