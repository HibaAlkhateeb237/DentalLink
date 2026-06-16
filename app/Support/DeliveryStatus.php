<?php

namespace App\Support;

final class DeliveryStatus
{
    public const EMPTY = 'empty';

    public const RECEIVED = 'received';

    public const ON_THE_WAY_TO_DOCTOR = 'on_the_way_to_the_doctor';

    public const ON_THE_WAY_TO_LAB = 'on_the_way_to_the_lab';

    public const DELIVERED = 'delivered';

    /** @var string[] */
    public const ALL = [
        self::EMPTY,
        self::RECEIVED,
        self::ON_THE_WAY_TO_DOCTOR,
        self::ON_THE_WAY_TO_LAB,
        self::DELIVERED,
    ];

    /** @var string[] */
    public const PICKED_STATUSES = [
        self::RECEIVED,
        self::ON_THE_WAY_TO_DOCTOR,
        self::ON_THE_WAY_TO_LAB,
        self::DELIVERED,
    ];

    /** Statuses considered "assigned" (not yet delivered) */
    public const ASSIGNED_STATUSES = [
        self::EMPTY,
        self::RECEIVED,
        self::ON_THE_WAY_TO_DOCTOR,
        self::ON_THE_WAY_TO_LAB,
    ];

    private function __construct()
    {
        // static class
    }
}
