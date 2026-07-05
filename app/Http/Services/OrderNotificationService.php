<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Order\OrderCompleted;
use App\Notifications\Order\OrderNew;
use App\Notifications\Order\OrderProcessingStarted;
use Illuminate\Support\Collection;

class OrderNotificationService
{
    public function notifyOrderProcessingStarted(Order $order, string $triggerType = 'manual', bool $sendNotification = true): void
    {
        if ($sendNotification) {
            $doctor = $order->user;
            if ($doctor) {
                $doctor->notify(new OrderProcessingStarted($order, $triggerType));
            }
        }
    }

    public function notifyOrderCompleted(Order $order): void
    {
        $doctor = $order->user;
        if ($doctor) {
            $doctor->notify(new OrderCompleted($order));
        }
    }

    public function notifyReceptionist(Order $order, bool $sendNotification = true): void
    {
        if ($sendNotification) {
            $receptionists = $this->getOrderReceptionists($order);

            foreach ($receptionists as $receptionist) {
                $receptionist->notify(new OrderNew($order));
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function getOrderReceptionists(Order $order): Collection
    {
        $lab = $order->lab;

        if (! $lab) {
            return collect();
        }

        $receptionistRoleId = Role::query()
            ->where('name', 'receptionist')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if (! $receptionistRoleId) {
            return collect();
        }

        return User::query()
            ->whereHas('departmentUserRoles', function ($query) use ($lab, $receptionistRoleId): void {
                $query->where('role_id', $receptionistRoleId)
                    ->whereHas('department', function ($departmentQuery) use ($lab): void {
                        $departmentQuery->where('lab_id', $lab->id);
                    });
            })
            ->distinct()
            ->get();
    }
}
