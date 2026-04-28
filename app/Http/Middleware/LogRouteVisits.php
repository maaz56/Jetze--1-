<?php

namespace App\Http\Middleware;

use App\Events\ActivityLogged;
use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

class LogRouteVisits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
public function handle($request, Closure $next)
{
    if (auth()->check()) {
        $path = $request->path(); // e.g. "api/flight-providers"
        $params = $request->query(); // all query params as array

        // Skip if it's "api/flights" (with or without params)
        if (!str_starts_with($path, 'api/flights') && !str_starts_with($path, 'api/activity-logs')) {
            event(new ActivityLogged(
                auth()->id(),
                'Visited route',
                $path, 
                [
                    'details' => $params, 
                ]
            ));
        }
    }

    return $next($request);
}


}
