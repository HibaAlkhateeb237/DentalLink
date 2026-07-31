<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('tracking.doctor.{doctorId}', function (User $user, int $doctorId): bool {
    if ($user->hasRole('system_admin')) {
        return true;
    }

    return $user->id === $doctorId;
});
