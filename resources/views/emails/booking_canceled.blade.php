<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Canceled | Jetze</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    @php
        $flightData = $flightData ?? [];
        $provider = $flightData['provider'] ?? [];
        $sector = $provider['sector'] ?? ($booking->sector ?? 'N/A');
        $travelDateRaw = $provider['travel_date'] ?? ($booking->travel_date ?? null);
        $travelDate = $travelDateRaw ? date('d M Y', strtotime((string) $travelDateRaw)) : 'N/A';
        $bookingRef = $booking->itinerary_ref ?? $booking->pnr ?? ('BK-' . $booking->id);
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#0F4F75;padding:20px 24px;text-align:center;">
                            <img src="{{ asset('assets/logo.png') }}" alt="Jetze" width="150" style="height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <h2 style="margin:0 0 12px 0;color:#b91c1c;font-size:24px;">Booking Canceled</h2>
                            <p style="margin:0 0 18px 0;font-size:14px;color:#4b5563;">
                                Your booking has been canceled successfully.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#6b7280;">Booking Reference</td>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:600;">{{ $bookingRef }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#6b7280;">Status</td>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:600;">{{ ucfirst($booking->status ?? 'canceled') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#6b7280;">Route</td>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:600;">{{ $sector }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Travel Date</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:600;">{{ $travelDate }}</td>
                                </tr>
                            </table>

                            <p style="margin:18px 0 0 0;font-size:13px;color:#6b7280;">
                                If you need support regarding refunds or rebooking, please contact Jetze support.
                            </p>

                            <div style="margin-top:22px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;padding:10px 18px;background:#0F4F75;color:#ffffff;text-decoration:none;border-radius:999px;font-size:13px;font-weight:600;">
                                    View My Bookings
                                </a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

