<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Helper to store custom notifications in database.
     */
    public static function send($user, string $title, string $message, string $type = 'general', array $metadata = [])
    {
        if (!$user) {
            return;
        }

        $user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'type' => 'App\Notifications\CustomNotification',
            'data' => [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'metadata' => $metadata
            ]
        ]);
    }
}
