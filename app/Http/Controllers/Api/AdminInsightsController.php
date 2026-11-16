<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlightBookings;
use App\Models\FlightSearch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting for the admin panel: best customers, most-booked
 * airlines, sales channels and most-searched routes.
 */
class AdminInsightsController extends Controller
{
    /** Statuses that count as a completed sale. */
    private const SOLD_STATUSES = ['ticketed'];

    public function overview(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Only admins can view this.');

        [$from, $to] = $this->dateRange($request);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$this->bookingBreakdown($from, $to),
            'top_searches' => $this->topSearches($from, $to),
        ]);
    }

    /**
     * Sales channel of a booking: the supplier it was booked with. These are
     * the providers FlightController searches through FlightAggregationService
     * (and AT, which has its own streaming search). Keys are matched against
     * the lower-cased flight_provider saved on the booking.
     */
    private const PROVIDER_CHANNELS = [
        'travelport' => 'Travelport',
        'sabre' => 'Sabre',
        'flydubai' => 'flydubai',
        'airsial' => 'AirSial',
        'pia' => 'PIA',
        'oneapi' => 'OneApi',
        'hakimi' => 'Hakimi',
        'at' => 'AT',
    ];

    private function channel(?string $provider, ?string $contentSource): string
    {
        $provider = strtolower(trim((string) $provider));
        $source = strtolower((string) $contentSource);

        // Travelport and Sabre sell GDS and NDC content; show which one.
        foreach (['travelport', 'sabre'] as $gds) {
            if (str_contains($provider, $gds)) {
                $label = self::PROVIDER_CHANNELS[$gds];

                return str_contains($source, 'ndc') || str_contains($provider, 'ndc') ? "$label NDC" : "$label GDS";
            }
        }

        return self::PROVIDER_CHANNELS[$provider] ?? ($provider !== '' ? strtoupper($provider) : 'Other');
    }

    /**
     * One pass over the period's bookings, tallied three ways: by customer
     * (overall and per channel), by airline (with its channel split) and by
     * channel. The airline lives inside each booking's stored flight JSON, so
     * this is done in PHP, in chunks, to keep memory flat on large ranges.
     */
    private function bookingBreakdown(Carbon $from, Carbon $to): array
    {
        $agents = [];          // user_id => totals
        $agentsByChannel = []; // channel => user_id => totals
        $airlines = [];        // airline => totals + channels
        $channels = [];        // channel => totals

        $blank = fn () => ['bookings' => 0, 'ticketed' => 0, 'sales' => 0.0];
        $add = function (array &$row, bool $isSold, float $amount): void {
            $row['bookings']++;
            if ($isSold) {
                $row['ticketed']++;
                $row['sales'] += $amount;
            }
        };

        FlightBookings::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'canceled')
            ->select(['id', 'agent_id', 'flight_data', 'flight_provider', 'content_source', 'status', 'amount'])
            ->chunkById(200, function ($bookings) use (&$agents, &$agentsByChannel, &$airlines, &$channels, $blank, $add) {
                foreach ($bookings as $booking) {
                    $isSold = in_array(strtolower((string) $booking->status), self::SOLD_STATUSES, true);
                    $amount = (float) $booking->amount;
                    $channel = $this->channel($booking->flight_provider, $booking->content_source);
                    $airline = $this->airlineName($this->decode($booking->flight_data), $booking->flight_provider);

                    $channels[$channel] ??= $blank();
                    $add($channels[$channel], $isSold, $amount);

                    $airlines[$airline] ??= $blank() + ['channels' => []];
                    $add($airlines[$airline], $isSold, $amount);
                    $airlines[$airline]['channels'][$channel] = ($airlines[$airline]['channels'][$channel] ?? 0) + 1;

                    if ($booking->agent_id) {
                        $agents[$booking->agent_id] ??= $blank();
                        $add($agents[$booking->agent_id], $isSold, $amount);

                        $agentsByChannel[$channel][$booking->agent_id] ??= $blank();
                        $add($agentsByChannel[$channel][$booking->agent_id], $isSold, $amount);
                    }
                }
            });

        $names = $this->agentNames(array_keys($agents));

        // Best sellers first; bookings break ties (and rank customers with holds only).
        $rankAgents = function (array $rows) use ($names): array {
            $list = [];
            foreach ($rows as $agentId => $row) {
                $list[] = [
                    'agent_id' => $agentId,
                    'agent' => $names[$agentId] ?? ('User #' . $agentId),
                    'bookings' => $row['bookings'],
                    'ticketed' => $row['ticketed'],
                    'sales' => round($row['sales'], 2),
                ];
            }
            usort($list, fn ($a, $b) => [$b['sales'], $b['bookings']] <=> [$a['sales'], $a['bookings']]);

            return array_slice($list, 0, 10);
        };

        $airlineList = [];
        foreach ($airlines as $name => $row) {
            arsort($row['channels']);
            $airlineList[] = [
                'airline' => $name,
                'bookings' => $row['bookings'],
                'ticketed' => $row['ticketed'],
                'sales' => round($row['sales'], 2),
                'channels' => collect($row['channels'])->map(fn ($count, $channel) => ['channel' => $channel, 'bookings' => $count])->values()->all(),
            ];
        }
        usort($airlineList, fn ($a, $b) => [$b['bookings'], $b['sales']] <=> [$a['bookings'], $a['sales']]);

        $channelList = [];
        foreach ($channels as $name => $row) {
            $channelList[] = ['channel' => $name, 'bookings' => $row['bookings'], 'ticketed' => $row['ticketed'], 'sales' => round($row['sales'], 2)];
        }
        usort($channelList, fn ($a, $b) => $b['bookings'] <=> $a['bookings']);

        return [
            'top_agents' => $rankAgents($agents),
            'top_agents_by_channel' => collect($agentsByChannel)->map(fn ($rows) => $rankAgents($rows))->all(),
            'top_airlines' => array_slice($airlineList, 0, 10),
            'channels' => $channelList,
        ];
    }

    /**
     * Searches carry a route (origin/destination) but not an airline, so the
     * ranking is by route. Rows come from the flight_searches table.
     */
    private function topSearches(Carbon $from, Carbon $to): array
    {
        $base = FlightSearch::query()->whereBetween('created_at', [$from, $to]);

        $routes = (clone $base)
            ->select('origin', 'destination')
            ->selectRaw('COUNT(*) as searches')
            ->selectRaw('COUNT(DISTINCT COALESCE(user_id, 0)) as users')
            ->groupBy('origin', 'destination')
            ->orderByDesc('searches')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'origin' => $row->origin,
                'destination' => $row->destination,
                'searches' => (int) $row->searches,
                'agents' => (int) $row->users,
            ])
            ->all();

        return ['total' => (clone $base)->count(), 'routes' => $routes];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Company name where the user has one, otherwise the user's own name. */
    private function agentNames(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        $companies = DB::table('agents_data')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('company_name')
            ->pluck('company_name', 'user_id');

        return User::whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn ($user) => [
                $user->id => $companies[$user->id] ?? $user->name ?? $user->email,
            ])
            ->all();
    }

    private function airlineName(?array $flightData, ?string $fallback): string
    {
        $name = data_get($flightData, 'leg.flights.0.marketing_carrier.name')
            ?? data_get($flightData, 'legs.0.stops.0.airline.name')
            ?? data_get($flightData, 'airline.name');

        return trim((string) ($name ?: $fallback ?: 'Unknown')) ?: 'Unknown';
    }

    private function decode($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function dateRange(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
