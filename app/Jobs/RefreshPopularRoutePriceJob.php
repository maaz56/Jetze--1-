<?php

namespace App\Jobs;

use App\Models\PopularRoute;
use App\Services\PopularRoutesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshPopularRoutePriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public int $popularRouteId
    ) {
        $this->onQueue('pricing');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('popular-route-price-' . $this->popularRouteId))
                ->expireAfter(120),
        ];
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

        if ($route->price_type !== 'dynamic') {
            return;
        }

        $popularRoutesService->refreshDynamicPrice($route);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RefreshPopularRoutePriceJob failed permanently', [
            'popular_route_id' => $this->popularRouteId,
            'error' => $exception?->getMessage(),
        ]);

        $route = PopularRoute::find($this->popularRouteId);
        if ($route) {
            $route->forceFill([
                'price_checked_at' => now(),
                'price_status' => 'failed',
                'price_last_error' => substr($exception?->getMessage() ?: 'Job timed out or failed', 0, 500),
                'next_price_refresh_at' => now()->addHours(2),
            ])->saveQuietly();
        }
    }
}
