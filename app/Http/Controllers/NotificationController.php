<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::query()->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->latest()
            ->get();

        return $this->apiResponse->success(
            NotificationResource::collection($notifications),
            __('notifications.retrieved_successfully'),
        );
    }
}
