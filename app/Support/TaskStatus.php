<?php

namespace App\Support;

final class TaskStatus
{

    public const PENDING_ASSIGNMENT = 'pending_assignment';

    public const ASSIGNED = 'assigned';

    public const IN_PROGRESS = 'in_progress';

    public const PENDING_REVIEW = 'pending_review';

    public const COMPLETED = 'completed';

    public const ALL = [
        self::PENDING_ASSIGNMENT,
        self::ASSIGNED,
        self::IN_PROGRESS,
        self::PENDING_REVIEW,
        self::COMPLETED,
    ];

    private function __construct() {}
}
