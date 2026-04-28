<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Offline Booking Confirmation</title>
</head>

<body
    style="margin:0; padding:0; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; background-color:#EBEFF3; color:#333; line-height:1.6;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <table align="center" cellpadding="0" cellspacing="0" width="600"
                    style="background-color:#fff; border-radius:8px; margin:20px auto; box-shadow:0 4px 8px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding:20px; text-align:center;">
                            <img src="{{ asset('assets/logo.png') }}" alt="Company Logo" width="150">
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding:30px;">
                            <h1 style="font-size:24px; color:#0F4F75; margin-bottom:20px;">Offline Booking Created</h1>
                            <p style="font-size:16px; margin-bottom:15px;">
                                Dear <strong style="color:#A43734;">{{ $booking->user->name }}</strong>,
                            </p>
                            <p style="font-size:16px; margin-bottom:25px;">
                                Thank you for booking with <strong>Jetze</strong>. Your offline booking has been
                                recorded successfully.
                            </p>

                            <!-- Booking Details -->
                            <table cellpadding="6" cellspacing="0" width="100%"
                                style="font-size:15px; border-collapse:collapse;">
                                <tr>
                                    <td><strong>Booking ID:</strong> {{ $booking->id }}</td>
                                    <td><strong>Status:</strong> {{ ucfirst($booking->status) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Flight Type:</strong> {{ ucfirst($booking->flight_type) }}</td>
                                    <td><strong>Class:</strong> {{ $booking->class_type }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Passengers:</strong> {{ $booking->adult }} Adult(s),
                                        {{ $booking->child }} Child(ren), {{ $booking->infant }} Infant(s)</td>
                                    <td><strong>Amount:</strong> {{ $booking->amount ?? 'N/A' }}</td>
                                </tr>
                            </table>

                            <!-- Route Info -->
                            <h3 style="margin-top:30px; color:#0F4F75;">Route Details</h3>
                            @php
                                $routes = json_decode($booking->route, true);
                                if (isset($routes['origin'])) {
                                    $routes = [$routes]; // normalize one-way
                                }
                            @endphp

                            <table cellpadding="8" cellspacing="0" width="100%"
                                style="border-collapse:collapse; font-size:14px; margin-top:10px;">
                                <thead>
                                    <tr style="background:#f3f4f6;">
                                        <th align="left">Date</th>
                                        <th align="left">From</th>
                                        <th align="left">To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($routes as $r)
                                        <tr>
                                            <td>{{ $r['date'] ?? ($r['dateRange']['start'] ?? '-') }}</td>
                                            <td>{{ $r['origin'] }}</td>
                                            <td>{{ $r['destination'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Traveller Info -->
                            @if($booking->travellers && $booking->travellers->count())
                                <h3 style="margin-top:30px; color:#0F4F75;">Traveller Details</h3>
                                <table cellpadding="8" cellspacing="0" width="100%"
                                    style="border-collapse:collapse; font-size:14px; margin-top:10px;">
                                    <thead>
                                        <tr style="background:#f3f4f6;">
                                            <th align="left">Name</th>
                                            <th align="left">DOB</th>
                                            <th align="left">Gender</th>
                                            <th align="left">Type</th>
                                            <th align="left">Nationality</th>
                                            <th align="left">Document</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($booking->travellers as $traveller)
                                            <tr>
                                                <td>{{ $traveller->title }} {{ $traveller->first_name }}
                                                    {{ $traveller->last_name }}</td>
                                                <td>{{ $traveller->dob }}</td>
                                                <td>{{ $traveller->gender }}</td>
                                                <td>{{ $traveller->type }}</td>
                                                <td>{{ strtoupper($traveller->nationality) }}</td>
                                                <td>
                                                    {{ ucfirst($traveller->document_type) }}: {{ $traveller->document_no }}<br>
                                                    Exp: {{ $traveller->expiry_date }}
                                                    ({{ strtoupper($traveller->issue_country) }})
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                            @endif

                            <p style="margin-top:30px; font-size:16px;">We’ll notify you once your booking is ticketed.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding:20px; text-align:center; background:#EBEFF3; font-size:13px; color:#666; border-bottom-left-radius:8px; border-bottom-right-radius:8px;">
                            <p style="margin:0;">© {{ date('Y') }} Jetze. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
