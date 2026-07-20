<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    public function getDoctorOrdersPaginated(User $doctor, ?string $paymentFilter = null, int $page = 1, int $perPage = 15): ?array
    {
        $labIds = $this->resolveLabIds();

        $doctor = $this->doctorsQuery($labIds, null, $paymentFilter)
            ->where('users.id', $doctor->id)
            ->first();

        if ($doctor === null) {
            return null;
        }

        $paginator = $this->buildOrdersQuery($doctor, $labIds, $paymentFilter)
            ->paginate($perPage, page: $page);

        $allOrders = $this->buildOrdersQuery($doctor, $labIds, $paymentFilter)
            ->with(['payments' => function ($paymentsQuery): void {
                $paymentsQuery->where('payment_status', 'paid');
            }])
            ->get();

        $doctor->setRelation('orders', $paginator->getCollection());
        $doctor->setRelation('allOrders', $allOrders);

        return [
            'doctor' => $doctor,
            'paginator' => $paginator,
        ];
    }

    /**
     * Build the constrained orders query for a given doctor.
     *
     * @return Builder<Order>
     */
    private function buildOrdersQuery(User $doctor, \Illuminate\Support\Collection $labIds, ?string $paymentFilter = null): Builder
    {
        return Order::query()
            ->where('user_id', $doctor->id)
            ->whereIn('lab_id', $labIds->all())
            ->where('is_in_delivery', false)
            ->when($paymentFilter === 'paid', static function ($paidQuery): void {
                $paidQuery->where('remaining_amount', '<=', 0);
            })
            ->when($paymentFilter === 'unpaid', static function ($unpaidQuery): void {
                $unpaidQuery->where('remaining_amount', '>', 0);
            })
            ->orderByDesc('created_at');
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
            $this->applyOrdersConstraints($query, $labIds, $paymentFilter);
        }]);
    }

    /**
     * Apply the shared lab/paid/unpaid/payments constraints to an orders query.
     */
    private function applyOrdersConstraints(Relation $query, \Illuminate\Support\Collection $labIds, ?string $paymentFilter = null): void
    {
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
