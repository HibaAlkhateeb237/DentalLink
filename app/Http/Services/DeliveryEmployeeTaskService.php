<?php

namespace App\Http\Services;

use App\Models\DeliveryTask;
use App\Models\User;
use App\Support\DeliveryStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryEmployeeTaskService
{
    /**
     * @param  array{tab?:string,direction?:string,per_page?:int}  $validated
     */
    public function listTasks(User $deliveryUser, array $validated): LengthAwarePaginator
    {
        $query = $deliveryUser->deliveryTasks()
            ->with(['order.user', 'user'])
            ->orderByDesc('id');

        $tab = $validated['tab'] ?? 'assigned';

        if ($tab === 'completed') {
            $query->where('status', DeliveryStatus::DELIVERED);
        } else {
            $query->whereIn('status', DeliveryStatus::ASSIGNED_STATUSES);
        }

        if (! empty($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    public function updateStatus(DeliveryTask $deliveryTask, string $newStatus): DeliveryTask
    {
        $updateData = ['status' => $newStatus];

        if ($newStatus === DeliveryStatus::RECEIVED) {
            $updateData['picked_at'] = now();
        }

        if ($newStatus === DeliveryStatus::DELIVERED) {
            $updateData['delivered_at'] = now();
        }

        $deliveryTask->update($updateData);

        return $deliveryTask->fresh()->load(['order.user', 'user']);
    }
}
