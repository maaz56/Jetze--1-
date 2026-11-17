
<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ModifyRequest;

/*
 * Every notification is delivered to the intended user's own private
 * channel. Never authorise a client to listen to another user's channel.
 */
Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('modify-requests.{id}', function ($user, $id) {
    $request = ModifyRequest::with('booking')->find($id);
    if (!$request) return false;

    $isRequestOwner = (int) $request->user_id === (int) $user->id;
    $canManageBookings = $user->role === 'admin' || $user->can('manage-bookings');

    return $isRequestOwner || $canManageBookings;
});
