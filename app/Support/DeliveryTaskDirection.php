<?php

namespace App\Support;

final class DeliveryTaskDirection
{
    public const TO_LAB = 'to_lab';

    public const TO_DOCTOR = 'to_doctor';

    /** @var string[] */
    public const ALL = [
        self::TO_LAB,
        self::TO_DOCTOR,
    ];

    private function __construct()
    {
        // static class
    }
}
