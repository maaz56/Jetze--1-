
<?php

// routes/channels.php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth; // Make sure Auth is imported

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
