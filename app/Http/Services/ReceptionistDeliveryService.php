<?php

namespace App\Http\Services;

use App\Models\DeliveryTask;
use App\Models\DepartmentUserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionistDeliveryService
{
    /**
     * @param  array{status?:string,search?:string,per_page?:int}  $validated
     */
    public function listDeliveryTasks(User $receptionist, array $validated): LengthAwarePaginator
    {
        $labId = $this->resolveReceptionistLabId($receptionist);

        $query = DeliveryTask::query()
            ->whereHas('order', function (Builder $builder) use ($labId): void {
                $builder->where('lab_id', $labId);
            })
            ->with(['user', 'order.user']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);

            $query->whereHas('user', function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @param  array{per_page?:int,search?:string}  $validated
     */
    public function listDeliveryEmployees(User $receptionist, array $validated): LengthAwarePaginator
    {
        $labId = $this->resolveReceptionistLabId($receptionist);

        $query = User::query()
            ->whereHas('departmentUserRoles', function (Builder $builder) use ($labId): void {
                $builder
                    ->whereHas('role', function (Builder $roleQuery): void {
                        $roleQuery
                            ->where('guard_name', 'sanctum')
                            ->where('name', 'delivery');
                    })
                    ->whereHas('department', function (Builder $departmentQuery) use ($labId): void {
                        $departmentQuery->where('lab_id', $labId);
                    });
            });

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        return $query->orderBy('id')->paginate($perPage);
    }

    public function assignDelivery(User $receptionist, Order $order, int $deliveryUserId): DeliveryTask
    {
        $labId = $this->resolveReceptionistLabId($receptionist);

        if ((int) $order->lab_id !== $labId) {
            throw ValidationException::withMessages([
                'order_id' => [__('auth.forbidden')],
            ]);
        }

        $deliveryUser = $this->findDeliveryUserInLab($deliveryUserId, $labId);

        if ($deliveryUser === null) {
            throw ValidationException::withMessages([
                'user_id' => [__('orders.delivery_user_invalid')],
            ]);
        }

        $existingAssignment = DeliveryTask::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->exists();

        if ($existingAssignment) {
            throw ValidationException::withMessages([
                'order_id' => [__('orders.delivery_already_assigned')],
            ]);
        }

        $deliveryTask = DB::transaction(function () use ($order, $deliveryUser): DeliveryTask {
            $order->update(['status' => 'pending']);

            return DeliveryTask::query()->create([
                'order_id' => $order->id,
                'user_id' => $deliveryUser->id,
                'status' => 'pending',
            ]);
        });

        return $deliveryTask->load('user:id,name,email,phone');
    }

    private function resolveReceptionistLabId(User $receptionist): int
    {
        $labId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $receptionist->id)
            ->where('roles.name', 'receptionist')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        if ($labId === null) {
            throw ValidationException::withMessages([
                'lab_id' => [__('messages.not_found')],
            ]);
        }

        return (int) $labId;
    }

    private function findDeliveryUserInLab(int $userId, int $labId): ?User
    {
        return User::query()
            ->where('id', $userId)
            ->whereHas('departmentUserRoles', function (Builder $builder) use ($labId): void {
                $builder
                    ->whereHas('role', function (Builder $roleQuery): void {
                        $roleQuery
                            ->where('guard_name', 'sanctum')
                            ->where('name', 'delivery');
                    })
                    ->whereHas('department', function (Builder $departmentQuery) use ($labId): void {
                        $departmentQuery->where('lab_id', $labId);
                    });
            })
            ->first();
    }
}
