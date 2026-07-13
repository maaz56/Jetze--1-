<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed | Jetze.pk</title>
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

            .flight-card {
                padding: 20px !important;
            }

            .flight-route-large {
                font-size: 22px !important;
            }

            .info-grid td {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                padding-bottom: 12px;
                border-right: none !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F0F2F5; color: #1A2C3E; line-height: 1.5;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
        
        <td style="padding: 40px 20px; background-color: #F0F2F5;">
            <table align="center" cellpadding="0" cellspacing="0" width="620" class="responsive-table"
                style="border-collapse: collapse; background-color: #FFFFFF; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.12); margin: 0 auto;">

                <!-- Brand Header with double accent -->
                <tr>
                    <td style="padding: 0;">
                        <div
                            style="background: #0F4F75; height: 5px;">
                        </div>
                        <div style="padding: 32px 32px 20px 32px; text-align: center; background: #FFFFFF;">
                            <img src="{{ asset('assets/logo.png') }}" alt="Jetze.pk" width="165"
                                style="height: auto; display: inline-block;">
                        </div>
                    </td>
                </tr>

                <!-- Hero Section -->
                <tr>
                    <td style="padding: 0 32px 20px 32px; text-align: center;">
                        @php
                            $flightData = $flightData ?? [];
                            $bookingRef = $booking->itinerary_ref ?? $booking->pnr ?? (($flightData['provider']['identifier'] ?? 'APNA') . rand(1000, 9999));
                            $leg = $flightData['leg'] ?? [];
                            $flights = $leg['flights'] ?? [];
                            $tripNature = $leg['trip_nature'] ?? 'international';
                            $providerName = $flightData['provider']['name'] ?? 'Travel Partner';
                            $totalFlights = count($flights);
                            $providerRaw = (string) ($booking->flight_provider ?? $flightData['provider']['name'] ?? '');
                            $providerLower = strtolower(trim($providerRaw));
                            $flightProvider = $providerLower === 'oneapi' ? 'OneApi' : ($providerLower === 'airblue' ? 'airblue' : 'travelport');
                            $flightMode = (string) ($booking->booking_mode ?? 'B2C');
                            $bookingSource = (string) ($booking->booking_source ?? 'web');
                            $bookingId = (string) ($booking->id ?? '');
                            $pnrValue = (string) ($booking->itinerary_ref ?? $booking->pnr ?? '');
                            $flightId = (string) ($booking->flight_id ?? '');
                            $isB2B = strtoupper($flightMode) === 'B2B';
                            $paymentPath = $isB2B ? '/agent/payment-view' : '/customer-payment-view';
                            $paymentParams = [
                                'booking_id' => $bookingId,
                                'flight_mode' => $flightMode,
                                'flight_provider' => $flightProvider,
                                'booking_source' => $bookingSource,
                                'pnr' => $pnrValue,
                            ];
                            if ($flightId !== '' && $flightId !== 'UNKNOWN') {
                                $paymentParams['flight_id'] = $flightId;
                            }
                            $payNowUrl = rtrim($loginUrl ?? config('app.frontend_url'), '/') . $paymentPath . '?' . http_build_query($paymentParams);
                            $selectSeatUrl = rtrim($loginUrl ?? config('app.frontend_url'), '/') . '/ancillaries/view?' . http_build_query($paymentParams);
                            $isAirblue = $providerLower === 'airblue';
                            $ancillariesSelectedRaw = strtolower(trim((string) ($booking->is_ancillaries_selected ?? '')));
                            $isAncillariesSelected = in_array($ancillariesSelectedRaw, ['1', 'true', 'yes'], true);
                            $showSelectSeatCta = $isAirblue && !$isAncillariesSelected;
                            $primaryCtaLabel = $showSelectSeatCta ? 'Select Seat' : 'Pay Now';
                            $primaryCtaUrl = $showSelectSeatCta ? $selectSeatUrl : $payNowUrl;
                            $ctaDescription = $showSelectSeatCta
                                ? 'Seat selection is mandatory before payment for Airblue bookings. Please select seats to continue.'
                                : 'Proceed directly to your booking payment screen, or log in to manage seats, baggage, and full itinerary details.';
                            $resolvedUserName = trim((string) ($userName ?? ''));
                            if ($resolvedUserName === '') {
                                $userFirstName = trim((string) ($booking->main_first_name ?? ''));
                                $userLastName = trim((string) ($booking->main_last_name ?? ''));
                                $resolvedUserName = trim($userFirstName . ' ' . $userLastName);
                            }
                            if ($resolvedUserName === '') {
                                $resolvedUserName = 'Valued Traveler';
                            }
                            $holdUntil = 'N/A';
                            if (!empty($booking->expiry_time)) {
                                $holdUntil = date('D, M j, Y g:i A', strtotime($booking->expiry_time));
                            }
                            $currency = strtoupper((string) ($booking->currency ?? ($flightData['price']['currency'] ?? 'PKR')));
                            $totalPaymentDue = number_format((float) ($booking->amount ?? 0), 2);
                        @endphp
                        <div
                            style="background: #EEF4F8; display: inline-block; padding: 6px 16px; border-radius: 40px; margin-bottom: 18px;">
                            <span style="font-size: 12px; font-weight: 600; color: #A43734; letter-spacing: 0.5px;">✓
                                On Hold • {{ strtoupper($bookingRef) }}</span>
                        </div>
                        <h1
                            style="margin: 0 0 10px 0; font-size: 34px; line-height: 1.2; color: #0F4F75; font-weight: 700; letter-spacing: -0.5px;">
                            Booking Is On Hold ✈️</h1>
                        <p style="margin: 0; font-size: 16px; color: #5A6E7F;">Your journey is reserved — we're ready
                            for takeoff!</p>
                        @if($totalFlights > 1)
                            <p style="margin: 12px 0 0 0; font-size: 14px; color: #A43734; font-weight: 500;">🔄
                                {{ $totalFlights }}-Flight Itinerary • {{ ucfirst($tripNature) }} Travel</p>
                        @endif
                    </td>
                </tr>

                <!-- Main Content -->
                <tr>
                    <td style="padding: 0 32px 32px 32px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                            style="border-collapse: collapse;">

                            <!-- Greeting -->
                            <tr>
                                <td style="padding-bottom: 24px;">
                                    <p style="margin: 0 0 6px 0; font-size: 18px; font-weight: 600; color: #1E2F3F;">
                                        Dear <span style="color: #A43734;">{{ $resolvedUserName }}</span>,</p>
                                    <p style="margin: 0; font-size: 15px; color: #4A627A;">Thank you for choosing
                                        Jetze.pk! Your flight booking is on hold. Please review your itinerary
                                        below:</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 24px;">
                                    <div style="background: #F8FBFF; border: 1px solid #E6EEF6; border-radius: 16px; padding: 16px 18px;">
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #6C869C;"><strong style="color: #1E2F3F;">Name:</strong> {{ $resolvedUserName }}</p>
                                        <p style="margin: 0 0 6px 0; font-size: 13px; color: #6C869C;"><strong style="color: #1E2F3F;">Booking Held Until:</strong> {{ $holdUntil }}</p>
                                        <p style="margin: 0; font-size: 13px; color: #6C869C;"><strong style="color: #1E2F3F;">Total Payment Due:</strong> {{ $currency }} {{ $totalPaymentDue }}</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- LOOP OVER MAIN FLIGHTS - Each flight displayed as a separate card -->
                            @forelse($flights as $flightIndex => $flight)
                                @php
                                    $toSafeString = function ($value, $fallback = '—') {
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

                                    // Extract main flight data (no segments breakdown)
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

                                    // Fare information (first fare option for this flight)
                                    $fares = $flight['fares'] ?? [];
                                    $selectedFare = $fares[0] ?? null;
                                    $currency = $selectedFare['currency'] ?? 'PKR';
                                    $totalPrice = $selectedFare['total_price'] ?? 0;
                                    $cabinClass = $selectedFare['cabin_class'][0] ?? ($flight['segments'][0]['cabin_class'] ?? 'Economy');
                                    $fareName = $selectedFare['name'] ?? 'Standard Fare';
                                    $baggagePolicies = $selectedFare['baggage_policies'] ?? [];

                                    // Format times
                                    $departureTimeFormatted = $departureAt ? date('g:i A', strtotime($departureAt)) : '—';
                                    $departureDateFormatted = $departureAt ? date('D, M j, Y', strtotime($departureAt)) : '—';
                                    $arrivalTimeFormatted = $arrivalAt ? date('g:i A', strtotime($arrivalAt)) : '—';
                                    $arrivalDateFormatted = $arrivalAt ? date('D, M j, Y', strtotime($arrivalAt)) : '—';

                                    // Calculate flight duration
                                    $durationText = '';
                                    if ($travelTime) {
                                        $hours = floor($travelTime / 60);
                                        $minutes = $travelTime % 60;
                                        $durationText = $hours . 'h ' . $minutes . 'm';
                                    }

                                    // Get baggage info
                                    $carryOnInfo = '';
                                    $checkedBagInfo = '';
                                   

                                    $isReturnFlight = $flightIndex > 0;
                                    $flightLabel = $totalFlights > 1 ? ($isReturnFlight ? 'RETURN FLIGHT' : 'OUTBOUND FLIGHT') : 'FLIGHT DETAILS';

                                    $operatingCarrierName = $toSafeString($operatingCarrier['name'] ?? null, 'Jetze.pk Airlines');
                                    $flightNumberText = $toSafeString($flightNumber, '—');
                                    $aircraftText = $toSafeString($aircraft, '');
                                    $cabinClassText = $toSafeString($cabinClass, 'Economy');
                                    $fromIataText = $toSafeString($fromAirport['iata'] ?? null, '—');
                                    $fromNameText = $toSafeString($fromAirport['name'] ?? null, 'Airport');
                                    $toIataText = $toSafeString($toAirport['iata'] ?? null, '—');
                                    $toNameText = $toSafeString($toAirport['name'] ?? null, 'Airport');
                                @endphp

                                <!-- Individual Flight Card -->
                                <tr>
                                    <td style="padding-bottom: 28px;">
                                        <div
                                            style="background: #FFFFFF; border-radius: 24px; border: 1px solid #E8EDF2; overflow: hidden; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03); transition: all 0.2s;">

                                            <!-- Flight Header with Label -->
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
                                                            <span
                                                                style="font-size: 14px; color: #6F8FAA; margin-left: 8px;">Flight
                                                                {{ $flightNumberText }}</span>
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

                                            <!-- Route Visualization - Main Flight Route -->
                                            <div style="padding: 28px 24px 24px 24px;">
                                                <table width="100%" cellpadding="0" cellspacing="0"
                                                    style="border-collapse: collapse;">
                                                    <tr>
                                                        <td style="text-align: center; width: 42%;">
                                                            <div>
                                                                <span
                                                                    style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #A43734;">DEPARTURE</span>
                                                                <p
                                                                    style="margin: 12px 0 4px 0; font-size: 32px; font-weight: 800; color: #0F4F75; letter-spacing: -0.5px;">
                                                                    {{ $fromIataText }}</p>
                                                                <p
                                                                    style="margin: 0; font-size: 12px; color: #6C869C; max-width: 160px; margin: 0 auto;">
                                                                    {{ $fromNameText }}</p>
                                                                <p
                                                                    style="margin: 12px 0 0 0; font-size: 15px; font-weight: 600; color: #2C4A6E;">
                                                                    {{ $departureTimeFormatted }}</p>
                                                                <p
                                                                    style="margin: 2px 0 0 0; font-size: 11px; color: #8FA0B0;">
                                                                    {{ $departureDateFormatted }}</p>
                                                            </div>
                                                        </td>
                                                        <td style="text-align: center; width: 16%;">
                                                            <div>
                                                                <div style="font-size: 32px; color: #A43734;">→</div>
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
                                                            </div>
                                                        </td>
                                                        <td style="text-align: center; width: 42%;">
                                                            <div>
                                                                <span
                                                                    style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #A43734;">ARRIVAL</span>
                                                                <p
                                                                    style="margin: 12px 0 4px 0; font-size: 32px; font-weight: 800; color: #0F4F75; letter-spacing: -0.5px;">
                                                                    {{ $toIataText }}</p>
                                                                <p
                                                                    style="margin: 0; font-size: 12px; color: #6C869C; max-width: 160px; margin: 0 auto;">
                                                                    {{ $toNameText }}</p>
                                                                <p
                                                                    style="margin: 12px 0 0 0; font-size: 15px; font-weight: 600; color: #2C4A6E;">
                                                                    {{ $arrivalTimeFormatted }}</p>
                                                                <p
                                                                    style="margin: 2px 0 0 0; font-size: 11px; color: #8FA0B0;">
                                                                    {{ $arrivalDateFormatted }}</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>


                                        </div>
                                    </td>
                                </tr>


                            @empty
                                <!-- Fallback if no flights found -->
                                <tr>
                                    <td style="padding-bottom: 28px;">
                                        <div
                                            style="background: #F8FAFC; border-radius: 20px; padding: 40px 24px; text-align: center; border: 1px dashed #A43734;">
                                            <p style="margin: 0; font-size: 16px; color: #0F4F75;">Flight details will be
                                                updated shortly</p>
                                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #7C8F9F;">Please check your
                                                booking dashboard for real-time updates.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse



                            <!-- CTA and Support -->
                            <tr>
                                <td style="padding-top: 8px;">
                                    <p style="margin: 0 0 24px 0; font-size: 14px; color: #4F6F8F; line-height: 1.5;">
                                        {{ $ctaDescription }}</p>

                                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                        style="border-collapse: collapse;">
                                        <tr class="stack-buttons">
                                            <td style="text-align: center; padding: 6px 8px;">
                                                <a href="{{ $primaryCtaUrl }}"
                                                    style="display: inline-block; padding: 14px 36px; background: #0F4F75; color: #FFFFFF; text-decoration: none; font-weight: 600; border-radius: 50px; font-size: 15px; box-shadow: 0 4px 12px rgba(10,46,77,0.2); min-width: 190px;">{{ $primaryCtaLabel }}</a>
                                            </td>
                                            <td style="text-align: center; padding: 6px 8px;">
                                                <a href="#"
                                                    style="display: inline-block; padding: 13px 32px; background: transparent; color: #0F4F75; text-decoration: none; font-weight: 600; border-radius: 50px; font-size: 15px; border: 1px solid #A43734;">Contact
                                                    Support</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <div
                                        style="margin-top: 32px; padding: 16px 20px; background: #F8FAFC; border-radius: 16px; border-left: 4px solid #A43734;">
                                        <p style="margin: 0; font-size: 13px; color: #7C5E2E;">✈️ <strong>Travel
                                                Tip:</strong> Please arrive at the airport at least 2-3 hours before
                                            departure. Carry a valid government ID and your booking reference: <strong
                                                style="color: #0F4F75;">{{ $bookingRef }}</strong></p>
                                    </div>

                                    <p style="margin: 28px 0 0 0; font-size: 15px; font-weight: 500; color: #0F4F75;">
                                        Bon Voyage,<br>The Jetze.pk Team</p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>

                <!-- Footer - Elegant & Professional -->
                <tr>
                    <td
                        style="background-color: #F9FBFD; border-top: 1px solid #E9EFF4; padding: 28px 32px 32px 32px; text-align: center;">

                        <!-- Social Links -->
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

                        <p style="margin: 0 0 12px 0; font-size: 13px; color: #6E8AA3;">📞 24/7 Support: +92 00000000
                            &nbsp;|&nbsp; ✉️ <a href="mailto:support@Jetze.pk"
                                style="color: #0F4F75; text-decoration: none;">support@Jetze.pk</a></p>

                        <div style="margin: 16px 0 12px 0;">
                            <a href="#"
                                style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Privacy
                                Policy</a>
                            <span style="color: #DAE2EA;">|</span>
                            <a href="#"
                                style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Terms of
                                Service</a>
                            <span style="color: #DAE2EA;">|</span>
                            <a href="#"
                                style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Unsubscribe</a>
                        </div>

                        <p style="margin: 20px 0 0 0; font-size: 11px; color: #9AB0C2;">© {{ date('Y') }} Jetze.pk.
                            All rights reserved. Your trusted travel partner.</p>
                        <p style="margin: 12px 0 0 0; font-size: 10px; color: #B2C4D4;">Reference: {{ $bookingRef }} •
                            This is a booking confirmation email. Please keep for your records.</p>
                    </td>
                </tr>

            </table>
            <div style="height: 8px;">&nbsp;</div>
        </td>
        </tr>
    </table>
</body>

</html>
