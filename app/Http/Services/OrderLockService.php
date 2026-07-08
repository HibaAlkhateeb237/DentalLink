<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class OrderLockService
{
    public const DEFAULT_TTL_SECONDS = 300;

    public const LOCK_PREFIX = 'order_lock:';

    public function lockKey(int $orderId): string
    {
        return self::LOCK_PREFIX.$orderId;
    }

    public function ownerKey(int $orderId): string
    {
        return self::LOCK_PREFIX.$orderId.':owner';
    }

    /**
     * Attempt to acquire a lock for the given order.
     *
     * @return array{success: bool, locked_by: int|null, locked_by_name: string|null, expires_at: string|null, message: string|null}
     */
    public function acquire(Order $order, User $user, ?int $ttl = null): array
    {
        $ttl = $ttl ?? self::DEFAULT_TTL_SECONDS;
        $lockKey = $this->lockKey($order->id);

        $lock = Cache::lock($lockKey, $ttl);

        if ($lock->get()) {
            Cache::put($this->ownerKey($order->id), [
                'user_id' => $user->id,
                'name' => $user->name,
                'acquired_at' => now()->toIso8601String(),
            ], $ttl);

            return [
                'success' => true,
                'locked_by' => $user->id,
                'locked_by_name' => $user->name,
                'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
                'message' => null,
            ];
        }

        $owner = $this->getOwner($order);

        return [
            'success' => false,
            'locked_by' => $owner['user_id'] ?? null,
            'locked_by_name' => $owner['name'] ?? null,
            'expires_at' => null,
            'message' => __('orders.order_locked_by_another', ['name' => $owner['name'] ?? 'another user']),
        ];
    }

    /**
     * Release a lock held by the given user.
     *
     * @return array{success: bool, message: string}
     */
    public function release(Order $order, User $user): array
    {
        $owner = $this->getOwner($order);

        if ($owner === null) {
            return [
                'success' => true,
                'message' => __('orders.order_unlocked'),
            ];
        }

        if ((int) ($owner['user_id'] ?? null) !== $user->id) {
            return [
                'success' => false,
                'message' => __('orders.order_locked_by_another', ['name' => $owner['name'] ?? 'another user']),
            ];
        }

        Cache::lock($this->lockKey($order->id))->forceRelease();
        Cache::forget($this->ownerKey($order->id));

        return [
            'success' => true,
            'message' => __('orders.order_unlocked'),
        ];
    }

    /**
     * Force release a lock regardless of owner (admin/system).
     */
    public function forceRelease(Order $order): void
    {
        Cache::lock($this->lockKey($order->id))->forceRelease();
        Cache::forget($this->ownerKey($order->id));
    }

    /**
     * @return array{user_id: int, name: string, acquired_at: string}|null
     */
    public function getOwner(Order $order): ?array
    {
        $owner = Cache::get($this->ownerKey($order->id));

        if (! is_array($owner)) {
            return null;
        }

        return $owner;
    }

    public function isLockedByOther(Order $order, User $user): bool
    {
        $owner = $this->getOwner($order);

        if ($owner === null) {
            return false;
        }

        return (int) ($owner['user_id'] ?? null) !== $user->id;
    }
}
