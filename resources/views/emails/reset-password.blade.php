<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Jetze</title>
    <style>
        @media only screen and (max-width: 600px) {
            .responsive-table {
                width: 100% !important;
            }

            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; background-color: #F0F2F5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1A2C3E; line-height: 1.5;">
    @php
        $resolvedResetUrl = trim((string) ($resetUrl ?? ''));
        if ($resolvedResetUrl === '') {
            $resolvedResetUrl = '#';
        }
    @endphp

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 20px; background-color: #F0F2F5;">
                <table align="center" cellpadding="0" cellspacing="0" width="620" class="responsive-table"
                    style="border-collapse: collapse; background-color: #FFFFFF; border-radius: 24px; overflow: hidden; box-shadow: 0 18px 34px -12px rgba(0, 0, 0, 0.12); margin: 0 auto;">

                    <tr>
                        <td style="padding: 0;">
                            <div style="background: #0F4F75; height: 6px;"></div>
                            <div style="padding: 30px 28px 18px 28px; text-align: center; background: #FFFFFF;">
                                <img src="{{ asset('assets/logo.png') }}" alt="Jetze" width="165"
                                    style="height: auto; display: inline-block;">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="mobile-padding" style="padding: 0 32px 16px 32px; text-align: center;">
                            <div
                                style="background: #EEF4F8; display: inline-block; padding: 6px 16px; border-radius: 40px; margin-bottom: 14px;">
                                <span style="font-size: 12px; font-weight: 600; color: #0F4F75; letter-spacing: 0.4px;">
                                    Account Security
                                </span>
                            </div>
                            <h1
                                style="margin: 0 0 10px 0; font-size: 30px; line-height: 1.25; color: #0F4F75; font-weight: 700; letter-spacing: -0.4px;">
                                Reset Your Password
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td class="mobile-padding" style="padding: 0 32px 30px 32px;">
                            <p style="margin: 0 0 12px 0; font-size: 14px; color: #4A627A;">
                                We received a request to reset your password. Click the button below to continue.
                            </p>

                            <div style="margin: 18px 0; text-align: center;">
                                <a href="{{ $resolvedResetUrl }}"
                                    style="display: inline-block; padding: 12px 24px; background: #A43734; color: #FFFFFF; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: 600;">
                                    Reset Password
                                </a>
                            </div>

                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #4A627A;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #7D8C9A; word-break: break-all;">
                                <a href="{{ $resolvedResetUrl }}"
                                    style="color: #0F4F75; text-decoration: underline;">{{ $resolvedResetUrl }}</a>
                            </p>

                            <p style="margin: 16px 0 0 0; font-size: 13px; color: #4A627A;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding: 18px 28px; background: #F8FAFC; border-top: 1px solid #D8E3EA; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #5A6E7F;">
                                © {{ now()->year }} Jetze. All rights reserved.
                            </p>
                            <p style="margin: 6px 0 0 0; font-size: 12px; color: #5A6E7F;">
                                Need help? <a href="mailto:support@Jetze.pk"
                                    style="color: #0F4F75; text-decoration: none;">support@Jetze.pk</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
