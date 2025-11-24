<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('whatsapp.{userId}', function ($user, $userId) {
    \Log::info('Broadcasting auth attempt', [
        'user_id' => $user ? $user->id : null,
        'requested_userId' => $userId,
        'authorized' => $user ? ((int) $user->id === (int) $userId) : false
    ]);

    return (int) $user->id === (int) $userId;
});
