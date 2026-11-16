
<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Every notification is delivered to the intended user's own private
 * channel. Never authorise a client to listen to another user's channel.
 */
Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
