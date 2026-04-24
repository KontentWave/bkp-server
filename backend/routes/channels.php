<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{id}', function ($user, string $id): bool {
    return (string) $user->getAuthIdentifier() === $id;
});
