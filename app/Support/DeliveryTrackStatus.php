<?php

namespace App\Support;

final class DeliveryTrackStatus
{
    public const PENDING = 'pending';

    public const STARTED = 'started';

    public const ARRIVED = 'arrived';

    public const CANCELLED = 'cancelled';

    /** @var string[] */
    public const ALL = [
        self::PENDING,
        self::STARTED,
        self::ARRIVED,
        self::CANCELLED,
    ];

    /** @var array<string, string[]> */
    public const VALID_TRANSITIONS = [
        self::PENDING => [self::STARTED, self::CANCELLED],
        self::STARTED => [self::ARRIVED, self::CANCELLED],
        self::ARRIVED => [],
        self::CANCELLED => [],
    ];

    private function __construct()
    {
        // static class
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public static function getNextAllowedStates(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }
}
