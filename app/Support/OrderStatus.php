<?php

namespace App\Support;

final class OrderStatus
{
    public const PENDING = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const TRY_ON = 'try_on';
    public const RESEND_WRONG_IMPRESSION = 'resend_wrong_impression';
    public const COMPLETED = 'completed';
   // public const DELIVERED = 'delivered';

    /** @var string[] */
    public const ALL = [
        self::PENDING,
        self::IN_PROGRESS,
        self::TRY_ON,
        self::RESEND_WRONG_IMPRESSION,
        self::COMPLETED,
       // self::DELIVERED,
    ];

    private function __construct()
    {
        // static class
    }
}
