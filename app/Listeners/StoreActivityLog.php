<?php

namespace App\Listeners;

use App\Events\ActivityLogged;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class StoreActivityLog
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
     public function handle(ActivityLogged $event)
    {
        ActivityLog::create([
            'user_id' => $event->userId,
            'action'  => $event->action,
            'route'   => $event->route,
            'details' => json_encode($event->details),
        ]);
    }
}
