<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popular Routes | Jetze</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-shell {
                width: 100% !important;
                border-radius: 0 !important;
            }

            .px-mobile {
                padding-left: 18px !important;
                padding-right: 18px !important;
            }
        }
    </style>
</head>

<body
    style="margin:0;padding:0;background:#F0F2F5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Helvetica,Arial,sans-serif;color:#1A2C3E;">
    @php
        $name = $recipientName ?: 'Traveler';
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:34px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" class="email-shell"
                    style="background:#FFFFFF;border-radius:26px;overflow:hidden;box-shadow:0 20px 36px rgba(15,73,117,0.16);">
                    <tr>
                        <td style="padding:0;">
                            <div style="height:5px;background:linear-gradient(90deg,#a43634 0%,#0F4975 100%);">
                            </div>
                            <div style="padding:30px 32px 20px 32px;text-align:center;">
                                <img src="{{ asset('assets/logo.png') }}" alt="Jetze" width="165"
                                    style="display:inline-block;height:auto;">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding:0 32px 30px 32px;">
                            <div
                                style="display:inline-block;padding:7px 16px;border-radius:40px;background:#F8E9E8;border:1px solid #E7C1C0;">
                                <span style="font-size:11px;font-weight:700;letter-spacing:.6px;color:#a43634;">
                                    POPULAR ROUTES
                                </span>
                            </div>

                            <h1 style="margin:14px 0 10px 0;font-size:30px;line-height:1.2;color:#0F4975;font-weight:800;">
                                Routes Travelers Are Booking
                            </h1>
                            <p style="margin:0 0 18px 0;font-size:15px;color:#56708A;line-height:1.6;">
                                Dear <strong style="color:#0F4975;">{{ $name }}</strong>, explore these selected
                                routes from Jetze.
                            </p>

                            @foreach($routes as $route)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                    style="margin-bottom:16px;border:1px solid #E7EEF5;border-radius:18px;overflow:hidden;background:#FFFFFF;">
                                    @if(!empty($route['image_url']))
                                        <tr>
                                            <td>
                                                <img src="{{ $route['image_url'] }}" alt="{{ $route['title'] }}"
                                                    width="620"
                                                    style="display:block;width:100%;max-height:230px;object-fit:cover;border:0;">
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td style="padding:18px 18px 20px 18px;">
                                            <p style="margin:0 0 8px 0;font-size:12px;color:#7D93A8;text-transform:uppercase;letter-spacing:.5px;">
                                                Airline Type: {{ $route['airline_type'] }} &middot; Journey: {{ $route['journey_type'] }} &middot; Class: {{ $route['travel_class'] }}
                                            </p>
                                            <h2 style="margin:0 0 8px 0;font-size:21px;line-height:1.35;color:#0F4975;font-weight:800;">
                                                {{ $route['title'] }}
                                            </h2>
                                            <p style="margin:0 0 14px 0;font-size:14px;color:#56708A;line-height:1.6;">
                                                {{ $route['from_city'] }} ({{ $route['from_airport'] }}) to
                                                {{ $route['to_city'] }} ({{ $route['to_airport'] }})
                                                @if(!empty($route['price']))
                                                    &middot; From PKR {{ $route['price'] }}
                                                @endif
                                            </p>
                                            <a href="{{ $route['url'] }}"
                                                style="display:inline-block;padding:10px 18px;border-radius:999px;background:#0F4975;color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:700;letter-spacing:.2px;">
                                                View Route
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endforeach

                            <div style="padding-top:8px;text-align:center;">
                                <a href="{{ $routesIndexUrl }}"
                                    style="display:inline-block;padding:12px 22px;border-radius:999px;background:#a43634;color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:.2px;">
                                    Visit Jetze
                                </a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile"
                            style="padding:22px 32px 28px 32px;border-top:1px solid #E9EFF4;background:#F9FBFD;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#7D93A8;">
                                You are receiving this email because you are connected with Jetze.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
