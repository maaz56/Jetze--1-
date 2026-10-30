@php
    $booking = $bookingRequest->booking;
    $currency = $bookingRequest->currency;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} - Booking Request Update</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f7f9fa; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #e1e8ed;">
        <h2 style="color: #002770; margin-top: 0;">{{ ucfirst($bookingRequest->type) }} Request {{ ucfirst($bookingRequest->status) }}</h2>
        <p>Dear {{ $bookingRequest->requester?->name ?? 'Customer' }},</p>
        <p>Your {{ $bookingRequest->type }} request for PNR <strong>{{ $booking?->itinerary_ref ?? $booking?->pnr ?? 'N/A' }}</strong> has been <strong>{{ $bookingRequest->status }}</strong>.</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px;">
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">Ticket Amount:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $currency }} {{ number_format((float) $bookingRequest->ticket_amount, 2) }}</td>
            </tr>
            @if($bookingRequest->status === 'approved')
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Penalty:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $currency }} {{ number_format((float) ($bookingRequest->penalty_amount ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Refund Amount:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; color: #16a34a; font-weight: bold;">{{ $currency }} {{ number_format((float) ($bookingRequest->refund_amount ?? 0), 2) }}</td>
            </tr>
            @endif
            @if($bookingRequest->admin_remarks)
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Remarks:</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bookingRequest->admin_remarks }}</td>
            </tr>
            @endif
        </table>

        <p style="font-size: 13px; color: #6b7280;">If you have any questions, please contact our support team.</p>
        <p style="font-size: 12px; color: #9ca3af;">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
