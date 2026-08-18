<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_assigns_receptionists_to_first_lab_reception_department(): void
    {
        $this->seed(DemoDataSeeder::class);

        $firstLab = Lab::query()->orderBy('id')->first();
        $this->assertNotNull($firstLab);

        $receptionistRoleId = Role::query()
            ->where('name', 'receptionist')
            ->where('guard_name', 'sanctum')
            ->value('id');
        $this->assertNotNull($receptionistRoleId);

        $receptionDepartment = Department::query()
            ->where('lab_id', $firstLab->id)
            ->where('name', 'الاستقبال')
            ->first();
        $this->assertNotNull($receptionDepartment);

        $receptionists = User::query()
            ->whereHas('roles', function ($query) use ($receptionistRoleId): void {
                $query->where('roles.id', $receptionistRoleId);
            })
            ->get();

        $this->assertSame(3, $receptionists->count());
        $assignedReceptionists = DepartmentUserRole::query()
            ->where('department_id', $receptionDepartment->id)
            ->where('role_id', $receptionistRoleId)
            ->pluck('user_id')
            ->all();

        $this->assertGreaterThanOrEqual(1, count($assignedReceptionists));
        $this->assertLessThanOrEqual(2, count($assignedReceptionists));
        $this->assertTrue($receptionists->whereIn('id', $assignedReceptionists)->count() === count($assignedReceptionists));
    }

    public function test_demo_data_seeder_assigns_one_manager_and_one_technician_to_each_first_lab_department(): void
    {
        $this->seed(DemoDataSeeder::class);

        $firstLab = Lab::query()->orderBy('id')->first();
        $this->assertNotNull($firstLab);

        $managerRoleId = Role::query()
            ->where('name', 'department_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');
        $this->assertNotNull($managerRoleId);

        $technicianRoleId = Role::query()
            ->where('name', 'lab_technician')
            ->where('guard_name', 'sanctum')
            ->value('id');
        $this->assertNotNull($technicianRoleId);

        $departments = Department::query()
            ->where('lab_id', $firstLab->id)
            ->where('is_management', false)
            ->whereNotIn('name', ['الاستقبال', 'التوصيل'])
            ->get();

        $this->assertNotEmpty($departments);
        // With 3 technicians per lab and 5 operational departments, only first 3 get technicians
        $this->assertCount(5, $departments);

        $departmentsWithTechnician = 0;
        $departmentsWithOnlyManager = 0;

        foreach ($departments as $department) {
            $assignments = DepartmentUserRole::query()
                ->where('department_id', $department->id)
                ->get();

            $managerCount = $assignments->where('role_id', $managerRoleId)->count();
            $technicianCount = $assignments->where('role_id', $technicianRoleId)->count();

            // Each department should have exactly 1 manager
            $this->assertSame(1, $managerCount, "Department {$department->name} should have 1 manager");

            // First 3 departments have technicians, last 2 don't
            if ($technicianCount === 1) {
                $departmentsWithTechnician++;
                $this->assertSame(2, $assignments->count());
            } else {
                $departmentsWithOnlyManager++;
                $this->assertSame(1, $assignments->count());
                $this->assertSame(0, $technicianCount);
            }
        }

        // 3 departments have both manager and technician, 2 have only manager
        $this->assertSame(3, $departmentsWithTechnician);
        $this->assertSame(2, $departmentsWithOnlyManager);
    }

    public function test_demo_data_seeder_assigns_each_lab_technician_to_only_one_department(): void
    {
        $this->seed(DemoDataSeeder::class);

        $technicianRoleId = Role::query()
            ->where('name', 'lab_technician')
            ->where('guard_name', 'sanctum')
            ->value('id');
        $this->assertNotNull($technicianRoleId);

        $technicians = User::query()
            ->whereHas('roles', function ($query) use ($technicianRoleId): void {
                $query->where('roles.id', $technicianRoleId);
            })
            ->get();

        $this->assertGreaterThanOrEqual(24, $technicians->count());

        foreach ($technicians as $technician) {
            $assignments = DepartmentUserRole::query()
                ->where('user_id', $technician->id)
                ->where('role_id', $technicianRoleId)
                ->get();

            $this->assertSame(1, $assignments->count());

            $department = Department::query()->find($assignments->first()->department_id);
            $this->assertNotNull($department);

            $lab = Lab::query()->find($department->lab_id);
            $this->assertNotNull($lab);
            $this->assertSame($lab->name, $technician->lab_name);
            $this->assertSame(0, (int) $department->is_management);
            $this->assertNotSame('الاستقبال', $department->name);
        }
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
                DepartmentUserRole::query()
                    ->where('department_id', $managementDepartment->id)
                    ->where('role_id', $labManagerRoleId)
                    ->exists()
            );
        }
    }

    public function test_demo_data_seeder_cleans_existing_order_qr_images(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('orders/stale-order/qr.png', 'stale');

        $this->seed(DemoDataSeeder::class);

        Storage::disk('public')->assertMissing('orders/stale-order/qr.png');
        $this->assertNotEmpty(Storage::disk('public')->allFiles('orders'));
    }

    public function test_demo_data_seeder_assigns_pre_created_stripe_accounts_to_labs(): void
    {
        $this->seed(DemoDataSeeder::class);

        $expectedAccountIds = [
            'acct_1Tu5f32F7fiRM0kt',
            'acct_1U1hVTCBpoWAMxEt',
            'acct_1U1hVbFr48catWJK',
            'acct_1U1hVfC6NE5EioXM',
            'acct_1U1hVjCFjCYiY5H3',
            'acct_1U1hVnC59piAVsvx',
            'acct_1U1hVr2FR143pRH8',
            'acct_1U1hVvCKm6COTOM5',
            'acct_1U1hVyCQn5Sdfwxb',
            'acct_1U1hW1FmxWaxvHxR',
        ];

        $labs = Lab::query()->orderBy('id')->get();
        $this->assertNotEmpty($labs);
        $this->assertCount(count($expectedAccountIds), $labs);

        foreach ($expectedAccountIds as $index => $accountId) {
            $this->assertSame($accountId, $labs[$index]->stripe_account_id);
        }
    }
}
