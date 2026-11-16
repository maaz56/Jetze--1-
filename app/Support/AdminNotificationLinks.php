<?php

namespace App\Support;

use App\Models\FlightBookings;

/**
 * Builds the admin-panel URLs that notifications link to.
 *
 * Each URL mirrors exactly what the matching admin list/sidebar page pushes
 * to the router (same route path and same query parameters), so opening a
 * notification lands on the same page and layout as clicking through the UI.
 */
class AdminNotificationLinks
{
    /** Same as TopUpRequests -> DepositDetails (?deposit_id=). */
    public static function deposit(int|string $depositId): string
    {
        return '/admin/deposit-details?'.http_build_query(['deposit_id' => $depositId]);
    }

    /** Same as Customers list -> CustomerDetails (?customer_id=). */
    public static function customer(int|string $userId): string
    {
        return '/admin/customer-details?'.http_build_query(['customer_id' => $userId]);
    }

    /** Same as Users list -> UserDetails (?user_id=). */
    public static function user(int|string $userId): string
    {
        return '/admin/user-details?'.http_build_query(['user_id' => $userId]);
    }

    /**
     * Same as the booking "details" buttons:
     *  - B2B (agent) bookings: Bookings list -> /admin/bookings-details
     *  - B2C (customer) bookings: Customer bookings / requests lists
     *    -> /admin/admin-customer-booking-layout
     * Both need provider, mode, source, agent and PNR to pick the right page.
     */
    public static function booking(FlightBookings $booking, array $extra = []): string
    {
        $mode = strtoupper((string) ($booking->booking_mode ?: 'B2C'));
        $pnr = $booking->itinerary_ref ?: $booking->pnr;

        if ($mode === 'B2B') {
            $flightData = json_decode((string) $booking->flight_data, true) ?: [];
            $provider = $flightData['provider']['name'] ?? $booking->flight_provider;

            return '/admin/bookings-details?'.http_build_query(array_filter([
                'booking_id' => $booking->id,
                'pnr' => $pnr,
                'agent_id' => $booking->agent_id,
                'booking_source' => $booking->booking_source,
                'flight_provider' => $provider,
                'flight_mode' => 'B2B',
            ] + $extra, fn ($v) => $v !== null && $v !== ''));
        }

        return '/admin/admin-customer-booking-layout?'.http_build_query(array_filter([
            'booking_id' => $booking->id,
            'pnr' => $pnr,
            'agent_id' => $booking->agent_id,
            'flight_mode' => 'B2C',
            'booking_source' => $booking->booking_source,
            'flight_provider' => $booking->flight_provider,
        ] + $extra, fn ($v) => $v !== null && $v !== ''));
    }
}
