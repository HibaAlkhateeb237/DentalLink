<?php

namespace App\Http\Services;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Builder;

class SystemLogService
{
    public function record(
        string $event,
        string $message,
        string $level = 'info',
        array $context = [],
        ?int $labId = null,
        ?int $userId = null
    ): SystemLog {
        return SystemLog::query()->create([
            'lab_id' => $labId,
            'user_id' => $userId,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'metadata' => $context === [] ? null : $context,
        ]);
    }

    public function info(string $event, string $message, array $context = [], ?int $labId = null, ?int $userId = null): SystemLog
    {
        return $this->record($event, $message, 'info', $context, $labId, $userId);
    }

    public function warning(string $event, string $message, array $context = [], ?int $labId = null, ?int $userId = null): SystemLog
    {
        return $this->record($event, $message, 'warning', $context, $labId, $userId);
    }

    public function error(string $event, string $message, array $context = [], ?int $labId = null, ?int $userId = null): SystemLog
    {
        return $this->record($event, $message, 'error', $context, $labId, $userId);
    }

    /** @param  array{level?: ?string, event?: ?string, user_id?: ?int, lab_id?: ?int}  $filters */
    public function query(array $filters = []): Builder
    {
        return SystemLog::query()
            ->with('user')
            ->when(! empty($filters['lab_id']), fn (Builder $query) => $query->where('lab_id', $filters['lab_id']))
            ->when(! empty($filters['level']), fn (Builder $query) => $query->where('level', $filters['level']))
            ->when(! empty($filters['event']), fn (Builder $query) => $query->where('event', $filters['event']))
            ->when(! empty($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->latest();
    }
}
