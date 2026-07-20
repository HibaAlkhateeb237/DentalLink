<?php

namespace App\Http\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class DoctorOrdersService
{
    /**
     * @return Collection<int, User>
     */
    public function getDoctorsWithOrders(?string $search = null): Collection
    {
        $labIds = $this->resolveLabIds();

        return $this->doctorsQuery($labIds, $search)->get();
    }

    public function getDoctorWithOrders(User $doctor, ?string $paymentFilter = null): ?User
    {
        $labIds = $this->resolveLabIds();

        return $this->doctorsQuery($labIds, null, $paymentFilter)
            ->where('users.id', $doctor->id)
            ->first();
    }

    /**
     * @return Builder<User>
     */
    private function doctorsQuery(\Illuminate\Support\Collection $labIds, ?string $search = null, ?string $paymentFilter = null): Builder
    {
        $doctorRoleId = Role::query()
            ->where('name', 'doctor')
            ->where('guard_name', 'sanctum')
            ->value('id');

        $doctorsQuery = User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.phone'])
            ->whereHas('orders', function ($query) use ($labIds): void {
                $query->whereIn('lab_id', $labIds->all())
                    ->where('is_in_delivery', false);
            })
            ->when($doctorRoleId !== null, function ($query) use ($doctorRoleId): void {
                $query->whereHas('roles', function ($roleQuery) use ($doctorRoleId): void {
                    $roleQuery->where('model_has_roles.role_id', $doctorRoleId)
                        ->where('model_has_roles.model_type', User::class);
                });
            })
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('users.name', 'like', '%'.$search.'%')
                        ->orWhere('users.email', 'like', '%'.$search.'%')
                        ->orWhere('users.phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('users.name');

        return $doctorsQuery->with(['orders' => function ($query) use ($labIds, $paymentFilter): void {
            $query->whereIn('lab_id', $labIds->all())
                ->where('is_in_delivery', false)
                ->when($paymentFilter === 'paid', static function ($paidQuery): void {
                    $paidQuery->where('remaining_amount', '<=', 0);
                })
                ->when($paymentFilter === 'unpaid', static function ($unpaidQuery): void {
                    $unpaidQuery->where('remaining_amount', '>', 0);
                })
                ->orderByDesc('created_at')
                ->with(['payments' => function ($paymentsQuery): void {
                    $paymentsQuery->where('payment_status', 'paid');
                }]);
        }]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function resolveLabIds(): \Illuminate\Support\Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        $user->loadMissing('departmentUserRoles.department');

        return $user->departmentUserRoles
            ->map(static fn ($departmentUserRole): ?int => $departmentUserRole->department?->lab_id)
            ->filter()
            ->unique()
            ->values();
    }
}
