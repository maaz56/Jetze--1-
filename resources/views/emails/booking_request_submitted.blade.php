@php
    $booking = $bookingRequest->booking;
    $currency = $bookingRequest->currency;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} - Booking Request Submitted</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f7f9fa; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #e1e8ed;">
        <h2 style="color: #002770; margin-top: 0;">New {{ ucfirst($bookingRequest->type) }} Request</h2>
        <p>A new {{ $bookingRequest->type }} request has been submitted by <strong>{{ $bookingRequest->requester?->name ?? 'Customer' }}</strong>.</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px;">
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">PNR / Ref:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $booking?->itinerary_ref ?? $booking?->pnr ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Request Type:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ strtoupper($bookingRequest->type) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Provider:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $booking?->flight_provider ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Passengers:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bookingRequest->passengers->map(fn ($p) => trim($p->first_name . ' ' . $p->last_name))->join(', ') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Ticket Amount:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $currency }} {{ number_format((float) $bookingRequest->ticket_amount, 2) }}</td>
            </tr>
            @if($bookingRequest->customer_remarks)
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Customer Remarks:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bookingRequest->customer_remarks }}</td>
            </tr>
            @endif
        </table>

        <p style="font-size: 13px; color: #6b7280;">Please log into the admin portal to approve or reject this request.</p>
        <p style="font-size: 12px; color: #9ca3af;">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
