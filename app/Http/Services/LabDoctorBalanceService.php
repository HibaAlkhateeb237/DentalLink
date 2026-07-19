<?php

namespace App\Http\Services;

use App\Models\DepartmentUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LabDoctorBalanceService
{
    public function resolveManagerLabId(User $manager): int
    {
        $labId = DepartmentUserRole::query()
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $manager->id)
            ->value('departments.lab_id');

        if ($labId === null) {
            abort(404, __('messages.not_found'));
        }

        return (int) $labId;
    }

    /**
     * @return Collection<int, User>
     */
    public function getDoctorBalances(int $labId, ?string $search = null): Collection
    {
        $doctorRoleId = Role::query()
            ->where('name', 'doctor')
            ->where('guard_name', 'sanctum')
            ->value('id');

        $baseQuery = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
            ])
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->where('orders.lab_id', $labId)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.phone');

        if ($search !== null && $search !== '') {
            $baseQuery->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%')
                    ->orWhere('users.phone', 'like', '%'.$search.'%');
            });
        }

        if ($doctorRoleId !== null) {
            $baseQuery->whereExists(function ($query) use ($doctorRoleId): void {
                $query->select(DB::raw('1'))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.role_id', $doctorRoleId);
            });
        }

        return $baseQuery
            ->selectRaw('COALESCE(SUM(orders.price), 0) as total_billed')
            ->selectRaw('COALESCE(SUM(orders.remaining_amount), 0) as total_remaining')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.price - orders.remaining_amount), 0) as total_paid')
            ->orderBy('users.name')
            ->get();
    }
}
