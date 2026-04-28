<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Details</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #ffffff; color: #333333; line-height: 1.6;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
        <tr>
            <td style="padding: 0;">
                <table align="center" cellpadding="0" cellspacing="0" width="600"
                    style="border-collapse: collapse; background-color: #ffffff; border-radius: 8px; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);">
                    <!-- Header with Logo -->
                    <tr>
                        <td
                            style="padding: 30px 0; text-align: center; background-color: #ffffff; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                            <img src="{{ asset('assets/logo.png') }}" alt="Company Logo" width="150"
                                style="height: auto; display: block; margin: 0 auto;">
                        </td>
                    </tr>

                    <!-- Content -->

                    <tr>
                        <td style="padding: 40px 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                style="border-collapse: collapse;">
                                <tr>
                                    <td>
                                        <h1
                                            style="margin: 0 0 20px 0; font-size: 24px; line-height: 1.2; color: #a8937a; font-weight: 600;">
                                            Charge Request –  Jetze
                                        </h1>

                                        <p style="margin: 0 0 25px 0; font-size: 16px;">
                                            To: <span
                                                style="font-weight: 600; color: #a8937a;"><b>{{ $charge->agent->email }}</b></span>,
                                        </p>

                                        <p style="margin: 0 0 25px 0; font-size: 16px;">
                                            A new charge request has been generated for {{ $charge->agent->email }} account. Please review the
                                            details below:
                                        </p>

                                        <p style="margin: 0 0 10px 0; font-size: 16px;">
                                            <b>Payment Type:</b> {{ $charge->payment_type }}
                                        </p>
                                        <p style="margin: 0 0 10px 0; font-size: 16px;">
                                            <b>Charge Description:</b> {{ $charge->additional_details }}
                                        </p>
                                        <p style="margin: 0 0 25px 0; font-size: 16px;">
                                            <b>Requested Amount:</b> {{ $charge->amount }} AED
                                        </p>

                                        <p style="margin: 0 0 25px 0; font-size: 16px;">
                                            Kindly log in to your account to review and take the necessary action
                                            regarding this request.
                                        </p>

                                        <p style="margin: 0; font-size: 16px;">
                                            Thank you for your prompt attention.<br>
                                            <b>Jetze</b>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>



                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding: 30px; background-color: #f8fafc; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; text-align: center; color: #6b7280;">
                            <p style="margin: 0 0 15px 0; font-size: 14px;">Thank you for joining us!</p>

                            <!-- Social Media Icons -->
                            {{-- <table role="presentation" cellpadding="0" cellspacing="0"
                                style="border-collapse: collapse; margin: 0 auto 15px auto;">
                                <tr>
                                    <td style="padding: 0 8px;">
                                        <a href="#"
                                            style="display: inline-block; width: 32px; height: 32px; background-color: #4f46e5; border-radius: 50%; text-align: center; line-height: 32px;">
                                            <img src="https://via.placeholder.com/16/ffffff/ffffff?text=f"
                                                alt="Facebook" width="16" height="16" style="vertical-align: middle;">
                                        </a>
                                    </td>
                                    <td style="padding: 0 8px;">
                                        <a href="#"
                                            style="display: inline-block; width: 32px; height: 32px; background-color: #4f46e5; border-radius: 50%; text-align: center; line-height: 32px;">
                                            <img src="https://via.placeholder.com/16/ffffff/ffffff?text=t" alt="Twitter"
                                                width="16" height="16" style="vertical-align: middle;">
                                        </a>
                                    </td>
                                    <td style="padding: 0 8px;">
                                        <a href="#"
                                            style="display: inline-block; width: 32px; height: 32px; background-color: #4f46e5; border-radius: 50%; text-align: center; line-height: 32px;">
                                            <img src="https://via.placeholder.com/16/ffffff/ffffff?text=in"
                                                alt="LinkedIn" width="16" height="16" style="vertical-align: middle;">
                                        </a>
                                    </td>
                                </tr>
                            </table> --}}

                            <p style="margin: 0 0 5px 0; font-size: 13px;">© 2025 Jetze. All rights reserved.</p>
                            <p style="margin: 0; font-size: 13px;">
                                <a href="#" style="color: #4f46e5; text-decoration: none;">Privacy Policy</a> |
                                <a href="#" style="color: #4f46e5; text-decoration: none;">Terms of Service</a> |
                                <a href="#" style="color: #4f46e5; text-decoration: none;">Unsubscribe</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>