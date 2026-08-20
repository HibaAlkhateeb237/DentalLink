<?php

namespace App\Http\Resources;

use App\Support\OrderDepartmentDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ReceptionistOrderDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startDate = $this->received_at;
        $endDate = $this->delivered_at;

        if ($endDate === null && $startDate !== null) {
            $estimatedHours = $this->estimatedTotalHours();
            $endDate = $estimatedHours > 0
                ? $startDate->copy()->addHours($estimatedHours)
                : null;
        }

        $elapsedMinutes = null;
        $remainingMinutes = null;
        $isOverdue = false;

        if ($startDate !== null) {
            $now = Carbon::now();
            $elapsedMinutes = max(0, (int) $startDate->diffInMinutes($now, false));

            if ($endDate !== null) {
                $remainingMinutes = (int) $now->diffInMinutes($endDate, false);
                $isOverdue = $remainingMinutes < 0;
                $remainingMinutes = abs($remainingMinutes);
            }
        }

        return [
            'id' => $this->id,
            'qr_code' => $this->qr_code,
            'case_type' => $this->case_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'order_type' => $this->order_type,
            'patient_name' => $this->patient_name,
            'serial_number' => $this->serial_number,
            'received_at' => $this->received_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'start_date' => $startDate?->toISOString(),
            'end_date' => $endDate?->toISOString(),
            'elapsed_time' => [
                'minutes' => $elapsedMinutes,
                'human' => $elapsedMinutes === null ? null : $this->humanizeMinutes($elapsedMinutes),
            ],
            'remaining_time' => [
                'minutes' => $remainingMinutes,
                'human' => $remainingMinutes === null ? null : $this->humanizeMinutes($remainingMinutes),
                'is_overdue' => $isOverdue,
            ],
            'estimated_total_hours' => $this->estimatedTotalHours(),
            'notes' => $this->notes,
            'price' => $this->price,
            'remaining_amount' => $this->remaining_amount,
            'paid_amount' => number_format((float) ($this->paid_amount ?? 0), 2, '.', ''),
            'is_paid' => (float) ($this->price ?? 0) > 0 && (float) ($this->remaining_amount ?? 0) <= 0,
            'before_image_path' => $this->whenLoaded('portfolioCase', fn () => $this->portfolioCase === null
                ? null
                : $this->toPublicUrl($this->portfolioCase->before_image_path)),
            'after_image_path' => $this->whenLoaded('portfolioCase', fn () => $this->portfolioCase === null
                ? null
                : $this->toPublicUrl($this->portfolioCase->after_image_path)),
            'requires_resubmission' => (bool) $this->requires_resubmission,
            'resubmission_reason' => $this->resubmission_reason,
            'resubmission_requested_at' => $this->resubmission_requested_at,
            'qr_printed_at' => $this->qr_printed_at?->toISOString(),
            'tooth_shade_name' => $this->toothShade?->name,
            'material_type' => $this->dentalCompensationTypePrice?->dentalCompensationType?->name,
            'case_name' => $this->whenLoaded('portfolioCase', fn () => $this->portfolioCase?->case_name),
            'is_published' => $this->whenLoaded('portfolioCase', fn () => (bool) $this->portfolioCase?->is_published),
            'portfolio_id' => $this->whenLoaded('portfolioCase', fn () => $this->portfolioCase?->id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'doctor' => $this->user === null
                ? null
                : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'location' => $this->user->location,
                ],
            'lab' => $this->lab === null
                ? null
                : [
                    'id' => $this->lab->id,
                    'name' => $this->lab->name,
                    'phone' => $this->lab->phone,
                    'address' => $this->lab->address,
                ],
            'teeth' => OrderToothResource::collection($this->whenLoaded('orderTeeth')),
            'files' => OrderFileResource::collection($this->whenLoaded('orderFiles')),
            'tasks' => $this->whenLoaded('tasks', function (): array {
                $tasksByDepartmentId = $this->tasks->keyBy('department_id');

                return collect(OrderDepartmentDisplay::departments($this->resource))
                    ->map(function (array $departmentEntry) use ($tasksByDepartmentId): array {
                        $task = $tasksByDepartmentId->get($departmentEntry['id']);

                        return [
                            'id' => $task?->id,
                            'status' => $departmentEntry['status'],
                            'approved_at' => $departmentEntry['approved_at'],
                            'department' => [
                                'id' => $departmentEntry['id'],
                                'name' => $departmentEntry['name'],
                            ],
                            'employee' => $task?->user === null
                                ? null
                                : [
                                    'id' => $task->user->id,
                                    'name' => $task->user->name,
                                    'email' => $task->user->email,
                                ],
                        ];
                    })
                    ->values()
                    ->all();
            }),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => $payment->pivot?->amount,
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at,
                'doctor' => $payment->user === null
                    ? null
                    : [
                        'id' => $payment->user->id,
                        'name' => $payment->user->name,
                        'email' => $payment->user->email,
                    ],
            ])->values()),
        ];
    }

    private function toPublicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith((string) $path, ['http://', 'https://'])) {
            return $path;
        }

        $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');

        return $publicDiskUrl !== ''
            ? $publicDiskUrl.'/'.ltrim((string) $path, '/')
            : '/storage/'.ltrim((string) $path, '/');
    }

    private function estimatedTotalHours(): int
    {
        if (! $this->lab || ! $this->lab->relationLoaded('departments')) {
            return 0;
        }

        return (int) $this->lab->departments
            ->where('is_management', false)
            ->sum(fn ($department) => (int) ($department->time_allowed ?? 0));
    }

    private function humanizeMinutes(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($mins > 0 || $parts === []) {
            $parts[] = $mins.'m';
        }

        return implode(' ', $parts);
    }
}
