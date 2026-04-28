<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | ETIMAD TRAVELS</title>
    <style>
        @media only screen and (max-width: 600px) {
            .responsive-table {
                width: 100% !important;
            }

            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .stack-item {
                display: block !important;
                width: 100% !important;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; background-color: #F0F2F5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1A2C3E; line-height: 1.5;">
    @php
        $resolvedCompany = trim((string) ($companyName ?? ''));
        if ($resolvedCompany === '') {
            $resolvedCompany = 'ETIMAD TRAVELS';
        }

        $resolvedName = trim((string) ($userName ?? ''));
        if ($resolvedName === '') {
            $resolvedName = 'User';
        }

        $resolvedEmail = trim((string) ($userEmail ?? ''));
        if ($resolvedEmail === '') {
            $resolvedEmail = 'N/A';
        }

        $resolvedTempPassword = trim((string) ($temporaryPassword ?? ''));
        if ($resolvedTempPassword === '') {
            $resolvedTempPassword = 'Use your registration password';
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
                                <img src="{{ asset('assets/logo.png') }}" alt="{{ $resolvedCompany }}" width="165"
                                    style="height: auto; display: inline-block;">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="mobile-padding" style="padding: 0 32px 18px 32px; text-align: center;">
                            <div
                                style="background: #EEF4F8; display: inline-block; padding: 6px 16px; border-radius: 40px; margin-bottom: 14px;">
                                <span style="font-size: 12px; font-weight: 600; color: #0F4F75; letter-spacing: 0.4px;">
                                    Account Activation
                                </span>
                            </div>
                            <h1
                                style="margin: 0 0 10px 0; font-size: 30px; line-height: 1.25; color: #0F4F75; font-weight: 700; letter-spacing: -0.4px;">
                                Welcome to {{ strtoupper($resolvedCompany) }}!
                            </h1>
                            <p style="margin: 0; font-size: 15px; color: #5A6E7F;">Your account
                                has been created.</p>
                        </td>
                    </tr>

                    <tr>
                        <td class="mobile-padding" style="padding: 0 32px 30px 32px;">
                            <p style="margin: 0 0 14px 0; font-size: 15px; color: #1E2F3F;">Hello <strong
                                    style="color: #A43734;">{{ strtoupper($resolvedName) }}</strong>,</p>

                            <p style="margin: 0 0 14px 0; font-size: 14px; color: #4A627A;">To activate your account,
                                please verify your email address.</p>

                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #1E2F3F; font-weight: 600;">Your
                                login credentials are:</p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                style="border-collapse: separate; border-spacing: 0 10px; margin-bottom: 8px;">
                                <tr>
                                    <td class="stack-item"
                                        style="background: #F8FAFC; border: 1px solid #D8E3EA; border-radius: 12px; padding: 12px 14px; font-size: 13px; color: #1E2F3F;">
                                        <strong>Email:</strong> {{ $resolvedEmail }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="stack-item"
                                        style="background: #F8FAFC; border: 1px solid #D8E3EA; border-radius: 12px; padding: 12px 14px; font-size: 13px; color: #1E2F3F;">
                                        <strong>Password:</strong> {{ $resolvedTempPassword }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 14px 0 0 0; font-size: 13px; color: #4A627A;"><strong>Important:</strong>
                                Please verify your email by clicking the button below:</p>

                            <div style="margin-top: 16px; text-align: center;">
                                <a href="{{ $url }}"
                                    style="display: inline-block; padding: 12px 24px; background: #A43734; color: #FFFFFF; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: 600;">
                                    Verify Email Address
                                </a>
                            </div>

                            <p style="margin: 20px 0 0 0; font-size: 12px; color: #7D8C9A; text-align: left;">
                                Or copy and paste this link into your browser:
                            </p>
                            <p
                                style="margin: 6px 0 0 0; font-size: 12px; color: #7D8C9A; text-align: left; word-break: break-all;">
                                <a href="{{ $url }}" style="color: #0F4F75; text-decoration: underline;">{{ $url }}</a>
                            </p>

                            <p style="margin: 16px 0 12px 0; font-size: 13px; color: #4A627A;">Once verified, you can
                                log in and will be required to change your password.</p>

                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #1E2F3F; font-weight: 600;">As a
                                our customer, you have the ability to:</p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                style="border-collapse: separate; border-spacing: 0 8px;">
                                
                                <tr>
                                    <td style="font-size: 13px; color: #1E2F3F;">• Make bookings</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #1E2F3F;">• View and manage bookings</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #1E2F3F;">• Make deposits and payments</td>
                                </tr>      
                                <tr>
                                    <td style="font-size: 13px; color: #1E2F3F;">• Access reports and analytics</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 18px 28px; background: #F8FAFC; border-top: 1px solid #D8E3EA; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #5A6E7F;">
                                © {{ now()->year }}  Jetze. All rights reserved.
                            </p>
                            <p style="margin: 6px 0 0 0; font-size: 12px; color: #5A6E7F;">
                                Need help? <a href="mailto:support@Jetze.pk" style="color: #0F4F75; text-decoration: none;">support@Jetze.pk</a>
                            </p>
                            <p style="margin: 6px 0 0 0; font-size: 12px; color: #5A6E7F;">
                                <a href="https://Jetze.com" style="color: #0F4F75; text-decoration: none;">www.Jetze.pk</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
