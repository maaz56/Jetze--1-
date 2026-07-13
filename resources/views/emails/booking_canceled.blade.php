<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Canceled | Jetze.pk</title>
    <style>
        @media only screen and (max-width: 600px) {
            .responsive-table {
                width: 100% !important;
            }

            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .stack-buttons td {
                display: block !important;
                width: 100% !important;
                margin-bottom: 12px;
                text-align: center;
            }

            .flight-route-large {
                font-size: 22px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F0F2F5; color: #1A2C3E; line-height: 1.5;">
    @php
        $flightData = $flightData ?? [];
        $bookingRef = $booking->itinerary_ref ?? $booking->pnr ?? ('BK-' . ($booking->id ?? rand(1000, 9999)));
        $leg = $flightData['leg'] ?? [];
        $flights = $leg['flights'] ?? [];
        $tripNature = $leg['trip_nature'] ?? 'international';
        $totalFlights = count($flights);

        $resolvedUserName = trim((string) ($userName ?? ''));
        if ($resolvedUserName === '') {
            $userFirstName = trim((string) ($booking->main_first_name ?? ''));
            $userLastName = trim((string) ($booking->main_last_name ?? ''));
            $resolvedUserName = trim($userFirstName . ' ' . $userLastName);
        }
        if ($resolvedUserName === '') {
            $resolvedUserName = (string) ($booking->main_name ?? 'Valued Traveler');
        }

        $currency = strtoupper((string) ($booking->currency ?? ($flightData['price']['currency'] ?? 'PKR')));
        $totalAmount = number_format((float) ($booking->amount ?? 0), 2);
        $canceledAt = !empty($booking->updated_at) ? date('D, M j, Y g:i A', strtotime($booking->updated_at)) : date('D, M j, Y g:i A');
        $frontend = rtrim($loginUrl ?? config('app.frontend_url'), '/');
        $bookingsUrl = $frontend . '/customer-bookings';
    @endphp

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 20px; background-color: #F0F2F5;">
                <table align="center" cellpadding="0" cellspacing="0" width="620" class="responsive-table"
                    style="border-collapse: collapse; background-color: #FFFFFF; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.12); margin: 0 auto;">

                    <tr>
                        <td style="padding: 0;">
                            <div style="background: #0F4F75; height: 5px;"></div>
                            <div style="padding: 32px 32px 20px 32px; text-align: center; background: #FFFFFF;">
                                <img src="{{ asset('assets/logo.png') }}" alt="Jetze.pk" width="165"
                                    style="height: auto; display: inline-block;">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 32px 20px 32px; text-align: center;" class="mobile-padding">
                            <div
                                style="background: #F8EDEA; display: inline-block; padding: 6px 16px; border-radius: 40px; margin-bottom: 18px;">
                                <span style="font-size: 12px; font-weight: 700; color: #A43734; letter-spacing: 0.5px;">
                                    CANCELED &bull; {{ strtoupper($bookingRef) }}
                                </span>
                            </div>
                            <h1
                                style="margin: 0 0 10px 0; font-size: 34px; line-height: 1.2; color: #0F4F75; font-weight: 700; letter-spacing: -0.5px;">
                                Booking Has Been Canceled</h1>
                            <p style="margin: 0; font-size: 16px; color: #5A6E7F;">
                                Your flight booking is no longer active. The canceled itinerary details are below.
                            </p>
                            @if($totalFlights > 1)
                                <p style="margin: 12px 0 0 0; font-size: 14px; color: #A43734; font-weight: 500;">
                                    {{ $totalFlights }}-Flight Itinerary &bull; {{ ucfirst($tripNature) }} Travel
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 32px 32px 32px;" class="mobile-padding">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                style="border-collapse: collapse;">
                                <tr>
                                    <td style="padding-bottom: 24px;">
                                        <p style="margin: 0 0 6px 0; font-size: 18px; font-weight: 600; color: #1E2F3F;">
                                            Dear <span style="color: #A43734;">{{ $resolvedUserName }}</span>,</p>
                                        <p style="margin: 0; font-size: 15px; color: #4A627A;">
                                            This is to confirm that your booking has been canceled. If you are eligible for
                                            a refund, our team will process it according to airline and fare rules.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom: 24px;">
                                        <div style="background: #F8FBFF; border: 1px solid #E6EEF6; border-radius: 16px; padding: 16px 18px;">
                                            <p style="margin: 0 0 6px 0; font-size: 13px; color: #6C869C;">
                                                <strong style="color: #1E2F3F;">Name:</strong> {{ $resolvedUserName }}
                                            </p>
                                            <p style="margin: 0 0 6px 0; font-size: 13px; color: #6C869C;">
                                                <strong style="color: #1E2F3F;">Booking Reference:</strong> {{ strtoupper($bookingRef) }}
                                            </p>
                                            <p style="margin: 0 0 6px 0; font-size: 13px; color: #6C869C;">
                                                <strong style="color: #1E2F3F;">Canceled On:</strong> {{ $canceledAt }}
                                            </p>
                                            <p style="margin: 0; font-size: 13px; color: #6C869C;">
                                                <strong style="color: #1E2F3F;">Booking Amount:</strong> {{ $currency }} {{ $totalAmount }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                @forelse($flights as $flightIndex => $flight)
                                    @php
                                        $toSafeString = function ($value, $fallback = '-') {
                                            if (is_string($value) || is_numeric($value)) {
                                                $text = trim((string) $value);
                                                return $text !== '' ? $text : $fallback;
                                            }

                                            if (is_array($value)) {
                                                $flat = [];
                                                array_walk_recursive($value, function ($item) use (&$flat) {
                                                    if (is_string($item) || is_numeric($item)) {
                                                        $text = trim((string) $item);
                                                        if ($text !== '') {
                                                            $flat[] = $text;
                                                        }
                                                    }
                                                });

                                                return !empty($flat) ? implode(' / ', $flat) : $fallback;
                                            }

                                            return $fallback;
                                        };

                                        $fromAirport = $flight['from'] ?? null;
                                        $toAirport = $flight['to'] ?? null;
                                        $departureAt = $flight['departure_at'] ?? null;
                                        $arrivalAt = $flight['arrival_at'] ?? null;
                                        $flightNumber = $flight['flight_number'] ?? null;
                                        $marketingCarrier = $flight['marketing_carrier'] ?? null;
                                        $operatingCarrier = $flight['segments'][0]['operating_carrier'] ?? $marketingCarrier ?? null;
                                        $aircraft = $flight['segments'][0]['aircraft'] ?? null;
                                        $travelTime = $flight['travel_time'] ?? null;
                                        $hasLayovers = $flight['has_layovers'] ?? false;
                                        $layoversCount = $flight['layovers_count'] ?? 0;
                                        $fares = $flight['fares'] ?? [];
                                        $selectedFare = $fares[0] ?? null;
                                        $cabinClass = $selectedFare['cabin_class'][0] ?? ($flight['segments'][0]['cabin_class'] ?? 'Economy');

                                        $departureTimeFormatted = $departureAt ? date('g:i A', strtotime($departureAt)) : '-';
                                        $departureDateFormatted = $departureAt ? date('D, M j, Y', strtotime($departureAt)) : '-';
                                        $arrivalTimeFormatted = $arrivalAt ? date('g:i A', strtotime($arrivalAt)) : '-';
                                        $arrivalDateFormatted = $arrivalAt ? date('D, M j, Y', strtotime($arrivalAt)) : '-';

                                        $durationText = '';
                                        if ($travelTime) {
                                            $hours = floor($travelTime / 60);
                                            $minutes = $travelTime % 60;
                                            $durationText = $hours . 'h ' . $minutes . 'm';
                                        }

                                        $isReturnFlight = $flightIndex > 0;
                                        $flightLabel = $totalFlights > 1 ? ($isReturnFlight ? 'RETURN FLIGHT' : 'OUTBOUND FLIGHT') : 'FLIGHT DETAILS';
                                        $operatingCarrierName = $toSafeString($operatingCarrier['name'] ?? null, 'Jetze.pk Airlines');
                                        $flightNumberText = $toSafeString($flightNumber);
                                        $aircraftText = $toSafeString($aircraft, '');
                                        $cabinClassText = $toSafeString($cabinClass, 'Economy');
                                        $fromIataText = $toSafeString($fromAirport['iata'] ?? null);
                                        $fromNameText = $toSafeString($fromAirport['name'] ?? null, 'Airport');
                                        $toIataText = $toSafeString($toAirport['iata'] ?? null);
                                        $toNameText = $toSafeString($toAirport['name'] ?? null, 'Airport');
                                    @endphp

                                    <tr>
                                        <td style="padding-bottom: 28px;">
                                            <div
                                                style="background: #FFFFFF; border-radius: 24px; border: 1px solid #E8EDF2; overflow: hidden; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);">
                                                <div
                                                    style="padding: 14px 24px; background: #F8FAFC; border-bottom: 1px solid #EFF3F8;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        style="border-collapse: collapse;">
                                                        <tr>
                                                            <td style="vertical-align: middle;">
                                                                @if($totalFlights > 1)
                                                                    <span
                                                                        style="background: {{ $isReturnFlight ? '#F8EDEA' : '#EEF4F8' }}; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: {{ $isReturnFlight ? '#A43734' : '#0F4F75' }}; letter-spacing: 0.5px;">{{ $flightLabel }}</span>
                                                                @endif
                                                                <span
                                                                    style="font-weight: 700; font-size: 16px; color: #1E2F3F; margin-left: {{ $totalFlights > 1 ? '12px' : '0' }};">{{ $operatingCarrierName }}</span>
                                                                <span style="font-size: 14px; color: #6F8FAA; margin-left: 8px;">
                                                                    Flight {{ $flightNumberText }}</span>
                                                            </td>
                                                            <td style="text-align: right; vertical-align: middle;">
                                                                @if($aircraftText !== '')
                                                                    <span
                                                                        style="font-size: 11px; color: #7C8F9F; background: #F0F3F8; padding: 4px 10px; border-radius: 20px;">{{ $aircraftText }}</span>
                                                                @endif
                                                                <span
                                                                    style="font-size: 12px; color: #A43734; margin-left: 10px; font-weight: 500;">{{ $cabinClassText }}</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>

                                                <div style="padding: 28px 24px 24px 24px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0"
                                                        style="border-collapse: collapse;">
                                                        <tr>
                                                            <td style="text-align: center; width: 42%;">
                                                                <span
                                                                    style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #A43734;">DEPARTURE</span>
                                                                <p class="flight-route-large"
                                                                    style="margin: 12px 0 4px 0; font-size: 32px; font-weight: 800; color: #0F4F75; letter-spacing: -0.5px;">
                                                                    {{ $fromIataText }}</p>
                                                                <p
                                                                    style="margin: 0; font-size: 12px; color: #6C869C; max-width: 160px; margin: 0 auto;">
                                                                    {{ $fromNameText }}</p>
                                                                <p
                                                                    style="margin: 12px 0 0 0; font-size: 15px; font-weight: 600; color: #2C4A6E;">
                                                                    {{ $departureTimeFormatted }}</p>
                                                                <p style="margin: 2px 0 0 0; font-size: 11px; color: #8FA0B0;">
                                                                    {{ $departureDateFormatted }}</p>
                                                            </td>
                                                            <td style="text-align: center; width: 16%;">
                                                                <div style="font-size: 32px; color: #A43734;">&rarr;</div>
                                                                <div
                                                                    style="font-size: 12px; font-weight: 500; color: #5F7D9A; margin-top: 8px;">
                                                                    {{ $durationText ?: 'Direct' }}</div>
                                                                @if($hasLayovers)
                                                                    <div
                                                                        style="font-size: 10px; color: #A43734; background: #F8EDEA; display: inline-block; padding: 3px 10px; border-radius: 20px; margin-top: 8px;">
                                                                        {{ $layoversCount }} stop(s)
                                                                    </div>
                                                                @else
                                                                    <div
                                                                        style="font-size: 10px; color: #2C8F6E; background: #E3F5EF; display: inline-block; padding: 3px 10px; border-radius: 20px; margin-top: 8px;">
                                                                        Non-stop
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            <td style="text-align: center; width: 42%;">
                                                                <span
                                                                    style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #A43734;">ARRIVAL</span>
                                                                <p class="flight-route-large"
                                                                    style="margin: 12px 0 4px 0; font-size: 32px; font-weight: 800; color: #0F4F75; letter-spacing: -0.5px;">
                                                                    {{ $toIataText }}</p>
                                                                <p
                                                                    style="margin: 0; font-size: 12px; color: #6C869C; max-width: 160px; margin: 0 auto;">
                                                                    {{ $toNameText }}</p>
                                                                <p
                                                                    style="margin: 12px 0 0 0; font-size: 15px; font-weight: 600; color: #2C4A6E;">
                                                                    {{ $arrivalTimeFormatted }}</p>
                                                                <p style="margin: 2px 0 0 0; font-size: 11px; color: #8FA0B0;">
                                                                    {{ $arrivalDateFormatted }}</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td style="padding-bottom: 28px;">
                                            <div
                                                style="background: #F8FAFC; border-radius: 20px; padding: 40px 24px; text-align: center; border: 1px dashed #A43734;">
                                                <p style="margin: 0; font-size: 16px; color: #0F4F75;">Canceled booking details</p>
                                                <p style="margin: 8px 0 0 0; font-size: 13px; color: #7C8F9F;">
                                                    Booking reference: <strong>{{ strtoupper($bookingRef) }}</strong>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse

                                <tr>
                                    <td style="padding-top: 8px;">
                                        <p style="margin: 0 0 24px 0; font-size: 14px; color: #4F6F8F; line-height: 1.5;">
                                            Need to rebook, request a refund update, or review this booking? Our support team is ready to help.
                                        </p>

                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                            style="border-collapse: collapse;">
                                            <tr class="stack-buttons">
                                                <td style="text-align: center; padding: 6px 8px;">
                                                    <a href="{{ $bookingsUrl }}"
                                                        style="display: inline-block; padding: 14px 36px; background: #0F4F75; color: #FFFFFF; text-decoration: none; font-weight: 600; border-radius: 50px; font-size: 15px; box-shadow: 0 4px 12px rgba(10,46,77,0.2); min-width: 190px;">View My Bookings</a>
                                                </td>
                                                <td style="text-align: center; padding: 6px 8px;">
                                                    <a href="mailto:support@Jetze.pk"
                                                        style="display: inline-block; padding: 13px 32px; background: transparent; color: #0F4F75; text-decoration: none; font-weight: 600; border-radius: 50px; font-size: 15px; border: 1px solid #A43734;">Contact Support</a>
                                                </td>
                                            </tr>
                                        </table>

                                        <div
                                            style="margin-top: 32px; padding: 16px 20px; background: #F8FAFC; border-radius: 16px; border-left: 4px solid #A43734;">
                                            <p style="margin: 0; font-size: 13px; color: #7C5E2E;">
                                                <strong>Refund note:</strong> Refund timelines and deductions depend on airline policy,
                                                fare rules, and payment method. Keep your booking reference
                                                <strong style="color: #0F4F75;">{{ strtoupper($bookingRef) }}</strong> for support.
                                            </p>
                                        </div>

                                        <p style="margin: 28px 0 0 0; font-size: 15px; font-weight: 500; color: #0F4F75;">
                                            Regards,<br>The Jetze.pk Team</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="background-color: #F9FBFD; border-top: 1px solid #E9EFF4; padding: 28px 32px 32px 32px; text-align: center;">
                            <table role="presentation" cellpadding="0" cellspacing="0"
                                style="border-collapse: collapse; margin: 0 auto 20px auto;">
                                <tr>
                                    <td style="padding: 0 8px;">
                                        <a href="#">
                                            <table role="presentation" width="38" height="38"
                                                style="background-color: #0F4F75; border-radius: 50%;">
                                                <tr>
                                                    <td align="center" valign="middle">
                                                        <img src="https://img.icons8.com/ios-filled/18/ffffff/facebook-new.png"
                                                            width="18" height="18" style="display:block;">
                                                    </td>
                                                </tr>
                                            </table>
                                        </a>
                                    </td>
                                    <td style="padding: 0 8px;">
                                        <a href="#">
                                            <table role="presentation" width="38" height="38"
                                                style="background-color: #0F4F75; border-radius: 50%;">
                                                <tr>
                                                    <td align="center" valign="middle">
                                                        <img src="https://img.icons8.com/ios-filled/18/ffffff/instagram-new.png"
                                                            width="18" height="18" style="display:block;">
                                                    </td>
                                                </tr>
                                            </table>
                                        </a>
                                    </td>
                                    <td style="padding: 0 8px;">
                                        <a href="#">
                                            <table role="presentation" width="38" height="38"
                                                style="background-color: #A43734; border-radius: 50%;">
                                                <tr>
                                                    <td align="center" valign="middle">
                                                        <img src="https://img.icons8.com/ios-filled/18/ffffff/twitter.png"
                                                            width="18" height="18" style="display:block;">
                                                    </td>
                                                </tr>
                                            </table>
                                        </a>
                                    </td>
                                    <td style="padding: 0 8px;">
                                        <a href="#">
                                            <table role="presentation" width="38" height="38"
                                                style="background-color: #0F4F75; border-radius: 50%;">
                                                <tr>
                                                    <td align="center" valign="middle">
                                                        <img src="https://img.icons8.com/ios-filled/18/ffffff/linkedin.png"
                                                            width="18" height="18" style="display:block;">
                                                    </td>
                                                </tr>
                                            </table>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #6E8AA3;">
                                24/7 Support: +92 00000000 &nbsp;|&nbsp;
                                <a href="mailto:support@Jetze.pk"
                                    style="color: #0F4F75; text-decoration: none;">support@Jetze.pk</a>
                            </p>

                            <div style="margin: 16px 0 12px 0;">
                                <a href="#"
                                    style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Privacy Policy</a>
                                <span style="color: #DAE2EA;">|</span>
                                <a href="#"
                                    style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Terms of Service</a>
                                <span style="color: #DAE2EA;">|</span>
                                <a href="#"
                                    style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Unsubscribe</a>
                            </div>

                            <p style="margin: 20px 0 0 0; font-size: 11px; color: #9AB0C2;">
                                © {{ date('Y') }} Jetze.pk. All rights reserved. Your trusted travel partner.
                            </p>
                            <p style="margin: 12px 0 0 0; font-size: 10px; color: #B2C4D4;">
                                Reference: {{ strtoupper($bookingRef) }} &bull; This is a booking cancellation email.
                            </p>
                        </td>
                    </tr>
                </table>
                <div style="height: 8px;">&nbsp;</div>
            </td>
        </tr>
    </table>
</body>

</html>
