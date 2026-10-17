<?php

namespace App\Console\Commands;

use App\Jobs\RefreshPopularRoutePriceJob;
use App\Models\PopularRoute;
use App\Services\PopularRoutesService;
use Illuminate\Console\Command;

class RefreshPopularRoutePrices extends Command
{
    protected $signature = 'popular-routes:refresh-prices {--route= : Specific route ID to refresh} {--force : Refresh even if not due} {--sync : Run synchronously instead of queue}';

    protected $description = 'Refresh dynamic flight prices for scheduled popular routes via AT';

    public function handle(PopularRoutesService $service): int
    {
        $routeId = $this->option('route');
        $force = $this->option('force');
        $sync = $this->option('sync');

        if ($routeId) {
            $routes = PopularRoute::where('id', $routeId)->get();
        } elseif ($force) {
            $routes = PopularRoute::where('price_type', 'dynamic')->limit(config('popular_routes.batch_size', 20))->get();
        } else {
            $routes = PopularRoute::dueForPriceRefresh()->limit(config('popular_routes.batch_size', 20))->get();
        }

        if ($routes->isEmpty()) {
            $this->info('No popular routes currently due for price refresh.');
            return self::SUCCESS;
        }

        $this->info("Found {$routes->count()} route(s) to refresh.");

        $staggerSeconds = (int) config('popular_routes.stagger_seconds', 10);

        foreach ($routes->values() as $i => $route) {
            if ($sync) {
                $this->line("Refreshing [{$route->id}] {$route->from_airport} -> {$route->to_airport} synchronously via AT...");
                try {
                    $result = $service->refreshDynamicPrice($route);
                    $this->info("Route [{$route->id}] status: {$result['status']} (Price: " . ($result['amount'] ?? 'N/A') . " " . ($result['currency'] ?? '') . ")");
                } catch (\Throwable $e) {
                    $this->error("Route [{$route->id}] failed: {$e->getMessage()}");
                }
            } else {
                $delay = $i * $staggerSeconds;
                RefreshPopularRoutePriceJob::dispatch($route->id)->delay(now()->addSeconds($delay));
                $this->line("Queued [{$route->id}] {$route->from_airport} -> {$route->to_airport} with delay {$delay}s.");
            }
        }

        $this->info('Done dispatching popular routes price refreshes.');
        return self::SUCCESS;
    }
}
