<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Notifications\Order\OrderProcessingStarted;
use App\Support\OrderStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionistOrderService
{
    public function __construct(private SystemLogService $systemLogs) {}

    /**
     * @param  array{per_page?:int,status?:string,priority?:string,doctor_id?:int,search?:string,from_date?:string,to_date?:string,sort_by?:string,sort_direction?:string,requires_resubmission?:bool}  $validated
     */
    public function listOrders(array $validated): LengthAwarePaginator
    {
        $query = Order::query()
            ->with([
                'user:id,name,email,phone,location',
                'lab:id,name,phone,address',
                'lab.departments' => fn ($q) => $q->select('id', 'lab_id', 'name', 'sort_order', 'is_management')->where('sort_order', '>', 0),
                'toothShade:id,name',
                'dentalCompensationTypePrice:id,dental_compensation_type_id',
                'dentalCompensationTypePrice.dentalCompensationType:id,name',
                'orderTeeth:id,order_id,tooth_number',
                'orderFiles:id,order_id,file_path,file_type,uploaded_at',
                'tasks:id,order_id,department_id,user_id,approved_at,status',
                'tasks.department:id,name,sort_order,time_allowed',
            ])
            ->withCount('orderTeeth')
            ->withSum('payments as paid_amount', 'payment_order.amount');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (isset($validated['doctor_id'])) {
            $query->where('user_id', $validated['doctor_id']);
        }

        $user = Auth::user();

        if ($user instanceof User) {
            $user->loadMissing('departmentUserRoles.department');

            $labIds = $user->departmentUserRoles
                ->map(static fn ($departmentUserRole): ?int => $departmentUserRole->department?->lab_id)
                ->filter()
                ->unique()
                ->values();

            $query->whereIn('lab_id', $labIds->all());
        }

        // Hide orders currently in delivery
        $query->where('is_in_delivery', false);

        if (array_key_exists('requires_resubmission', $validated)) {
            $query->where('requires_resubmission', (bool) $validated['requires_resubmission']);
        }

        if (isset($validated['from_date'])) {
            $query->whereDate('created_at', '>=', $validated['from_date']);
        }

        if (isset($validated['to_date'])) {
            $query->whereDate('created_at', '<=', $validated['to_date']);
        }

        if (isset($validated['search'])) {
            $search = trim($validated['search']);

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('qr_code', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('lab', function (Builder $labQuery) use ($search): void {
                        $labQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();
    }

    public function getOrderDetails(Order $order): Order
    {
        return $order->load([
            'user:id,name,email,phone,location',
            'lab:id,name,phone,address',
            'lab.departments' => fn ($query) => $query->where('sort_order', '>', 0)->orderBy('sort_order', 'asc')->select(['id', 'lab_id', 'name', 'description', 'is_management', 'sort_order', 'time_allowed']),
            'orderTeeth:id,order_id,tooth_number,notes',
            'orderFiles:id,order_id,file_path,file_type,uploaded_at',
            'tasks:id,order_id,department_id,user_id,approved_at,status',
            'tasks.department:id,name,lab_id',
            'tasks.user:id,name,email',
            'payments:id,user_id,amount,payment_method,paid_at',
            'payments.user:id,name,email',
            'toothShade:id,name',
            'dentalCompensationTypePrice:id,dental_compensation_type_id',
            'dentalCompensationTypePrice.dentalCompensationType:id,name',
            'portfolioCase:id,order_id,case_name,before_image_path,after_image_path,duration_minutes,is_published',
        ])->loadSum('payments as paid_amount', 'payment_order.amount');
    }

    public function markForResubmission(Order $order, string $reason, User $actor): Order
    {
        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => [__('orders.resubmission_not_allowed_for_status')],
            ]);
        }

        $order->fill([
            'requires_resubmission' => true,
            'resubmission_reason' => $reason,
            'resubmission_requested_at' => now(),
            'resubmission_requested_by' => $actor->id,
        ]);
        $order->save();

        return $this->getOrderDetails($order->fresh());
    }

    /**
     * Update order status and optional notes via a single transactional path and record history.
     *
     * @param  array{status?:string,notes?:string|null}  $data
     */
    public function updateStatusAndDetails(Order $order, array $data, ?User $actor = null): Order
    {
        $actorId = $actor?->id ?? Auth::id();

        $toStatus = $data['status'] ?? $order->status;

        if (! in_array($toStatus, OrderStatus::ALL, true)) {
            throw ValidationException::withMessages(['status' => ['Invalid status provided.']]);
        }

        DB::transaction(function () use ($order, $data, $actorId): void {
            $from = $order->status;

            if (isset($data['status'])) {
                $order->status = $data['status'];

                if ($from === OrderStatus::IN_PROGRESS && $data['status'] !== OrderStatus::IN_PROGRESS) {
                    $order->is_status_finalized = true;
                }

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => $from,
                    'to_status' => $data['status'],
                    'changed_by' => $actorId,
                    'reason' => $data['notes'] ?? null,
                    'metadata' => [
                        'triggered_via' => 'api',
                    ],
                ]);

                // Notify doctor when order status changes to resend_wrong_impression
                $this->notifyDoctorForOrderChanges($order, $from, $data['status']);

                // Delete all existing tasks when status changes to resend_wrong_impression
                if ($data['status'] === OrderStatus::RESEND_WRONG_IMPRESSION) {
                    $order->qr_printed_at = null;
                    $order->tasks()->delete();
                }
            }

            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            $order->save();
        });

        return $this->getOrderDetails($order->fresh());
    }

    /**
     * Notify doctor when order status changes to a state requiring notification.
     */
    private function notifyDoctorForOrderChanges(Order $order, string $fromStatus, string $toStatus): void
    {
        // Notify doctor when order status changes to resend_wrong_impression
        if ($toStatus === OrderStatus::RESEND_WRONG_IMPRESSION) {
            $doctor = $order->user;
            if ($doctor) {
                $doctor->notify(new OrderProcessingStarted($order, 'manual'));
            }
        }
    }

    /**
     * Update order status via a single transactional path and record history.
     */
    public function updateStatus(Order $order, string $toStatus, ?string $reason = null, ?User $actor = null): Order
    {
        $actorId = $actor?->id ?? Auth::id();

        // Basic guard: ensure provided status is valid
        if (! in_array($toStatus, OrderStatus::ALL, true)) {
            throw ValidationException::withMessages(['status' => ['Invalid status provided.']]);
        }

        DB::transaction(function () use ($order, $toStatus, $reason, $actorId): void {
            $from = $order->status;

            // update model
            $order->status = $toStatus;
            $order->save();

            // write history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'changed_by' => $actorId,
                'reason' => $reason,
                'metadata' => [
                    'triggered_via' => 'api',
                ],
            ]);

            // Notify doctor when order status changes to resend_wrong_impression
            $this->notifyDoctorForOrderChanges($order, $from, $toStatus);

            // Delete all existing tasks when status changes to resend_wrong_impression
            if ($toStatus === OrderStatus::RESEND_WRONG_IMPRESSION) {
                $order->qr_printed_at = null;
                $order->tasks()->delete();
            }

            $this->systemLogs->info(
                'order.status.changed',
                "Order {$order->serial_number} status changed from {$from} to {$toStatus}",
                [
                    'order_id' => $order->id,
                    'order_serial' => $order->serial_number,
                    'from_status' => $from,
                    'to_status' => $toStatus,
                    'reason' => $reason,
                ],
                $order->lab_id,
                $actorId,
            );
        });

        return $this->getOrderDetails($order->fresh());
    }
}
