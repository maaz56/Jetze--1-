<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlightBookings;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TravelCalendarController extends Controller
{
    public function calendar(Request $request)
    {
        $this->ensureAdmin($request);
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->month . '-01')
            : now();

        $days = FlightBookings::query()
            ->whereNotIn('flight_bookings.status', ['canceled', 'cancelled', 'voided'])
            ->whereBetween('travel_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->leftJoin('flight_passengers', 'flight_passengers.booking_id', '=', 'flight_bookings.id')
            ->selectRaw('flight_bookings.travel_date as day')
            ->selectRaw('COUNT(DISTINCT flight_bookings.id) as bookings')
            ->selectRaw('COUNT(flight_passengers.id) as travellers')
            ->groupBy('flight_bookings.travel_date')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::parse($row->day)->toDateString() => [
                    'bookings' => (int) $row->bookings,
                    'travellers' => (int) $row->travellers,
                ],
            ]);

        return response()->json(['month' => $month->format('Y-m'), 'days' => $days]);
    }

    public function travellers(Request $request)
    {
        $this->ensureAdmin($request);
        $request->validate(['date' => ['nullable', 'date']]);
        $date = $request->filled('date') ? Carbon::parse($request->date)->toDateString() : now()->toDateString();

        $bookings = FlightBookings::query()
            ->whereNotIn('status', ['canceled', 'cancelled', 'voided'])
            ->whereDate('travel_date', $date)
            ->with(['pessangers', 'user:id,name'])
            ->orderBy('id')
            ->get()
            ->map(function (FlightBookings $booking) {
                $flightData = json_decode((string) $booking->flight_data, true) ?: [];

                return [
                    'booking_id' => $booking->id,
                    // Match the admin customer-bookings listing.
                    'pnr' => $booking->itinerary_ref ?: $booking->pnr,
                    'airline_pnr' => $booking->airline_pnr,
                    'sector' => $booking->sector,
                    'status' => $booking->status,
                    'agent' => $booking->user?->name ?? '—',
                    'agent_id' => $booking->agent_id,
                    'booking_source' => $booking->booking_source,
                    'flight_provider' => $booking->flight_provider
                        ?? data_get($flightData, 'provider.name')
                        ?? 'N/A',
                    'departure_at' => data_get($flightData, 'leg.flights.0.departure_at')
                        ?? data_get($flightData, 'provider.travel_date')
                        ?? $booking->travel_date,
                    'passengers' => $booking->pessangers->map(fn ($passenger) => [
                        'name' => trim(implode(' ', array_filter([
                            $passenger->title,
                            $passenger->first_name,
                            $passenger->last_name,
                        ]))),
                        'type' => $passenger->type,
                        'ticket_no' => $passenger->ticket_no ?? $booking->ticket_number,
                    ])->values(),
                ];
            })->values();

        return response()->json([
            'date' => $date,
            'bookings' => $bookings,
            'total_bookings' => $bookings->count(),
            'total_travellers' => $bookings->sum(fn ($booking) => count($booking['passengers'])),
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
