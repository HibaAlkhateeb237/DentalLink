<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignLabManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assign:lab-manager {--user= : User ID} {--lab= : Lab ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a user to the management department of a lab with the lab_manager role';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $labId = (int) $this->option('lab');

        if ($userId <= 0 || $labId <= 0) {
            $this->error('You must provide both --user and --lab options with positive integers.');

            return 1;
        }

        $user = User::find($userId);

        if (! $user) {
            $this->error("User with id {$userId} not found.");

            return 1;
        }

        $department = Department::query()
            ->where('lab_id', $labId)
            ->where('is_management', 1)
            ->first();

        if (! $department) {
            $this->error("Management department for lab {$labId} not found.");

            return 1;
        }

        $roleId = DB::table('roles')
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if (! $roleId) {
            $this->error('lab_manager role (guard sanctum) not found in roles table.');

            return 1;
        }

        $exists = DB::table('department_user_roles')
            ->where('user_id', $userId)
            ->where('department_id', $department->id)
            ->where('role_id', $roleId)
            ->exists();

        if ($exists) {
            $this->info('User is already assigned as lab_manager for this department.');

            return 0;
        }

        DB::table('department_user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'department_id' => $department->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Assigned user {$user->id} ({$user->email}) as lab_manager to lab {$labId} (department {$department->id}).");

        return 0;
    }
}
