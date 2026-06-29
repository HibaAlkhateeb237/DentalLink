<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(protected FcmService $fcmService) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        /** @var array{title: string, body: string, data?: array} $fcmData */
        $fcmData = $notification->toFcm($notifiable);

        $this->fcmService->sendToUser(
            $notifiable->id,
            $fcmData['title'],
            $fcmData['body'],
            $fcmData['data'] ?? [],
        );
    }
}
