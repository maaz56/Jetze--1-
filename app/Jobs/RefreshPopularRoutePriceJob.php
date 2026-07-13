<?php

namespace App\Jobs;

use App\Models\PopularRoute;
use App\Services\PopularRoutesService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class RefreshPopularRoutePriceJob
{
    use Dispatchable;

    public function __construct(
        public int $popularRouteId
    ) {
    }

    public function handle(PopularRoutesService $popularRoutesService): void
    {
        $route = PopularRoute::with('airline')->find($this->popularRouteId);

        if (!$route) {
            Log::warning('Popular route refresh skipped because route was not found', [
                'popular_route_id' => $this->popularRouteId,
            ]);

            return;
        }

        $popularRoutesService->refreshDynamicPrice($route);

        $route->refresh();

        // if ($route->price_type === 'dynamic' && (int) $route->dynamic_refresh_hours > 0) {
        //     self::dispatch($route->id)->delay(now()->addHours((int) $route->dynamic_refresh_hours));
        // }
    }
}
