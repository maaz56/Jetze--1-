<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA Verification Code</title>
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
        $resolvedName = trim((string) ($name ?? 'User'));
        if ($resolvedName === '') {
            $resolvedName = 'User';
        }

        $resolvedOtp = trim((string) ($otp ?? '------'));
        if ($resolvedOtp === '') {
            $resolvedOtp = '------';
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
                                <img src="{{ asset('assets/logo.png') }}" alt="TravviseConnect" width="165"
                                    style="height: auto; display: inline-block;">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="mobile-padding" style="padding: 0 32px 16px 32px; text-align: center;">
                            <div
                                style="background: #EEF4F8; display: inline-block; padding: 6px 16px; border-radius: 40px; margin-bottom: 14px;">
                                <span style="font-size: 12px; font-weight: 600; color: #0F4F75; letter-spacing: 0.4px;">
                                    MFA Security
                                </span>
                            </div>
                            <h1
                                style="margin: 0 0 10px 0; font-size: 30px; line-height: 1.25; color: #0F4F75; font-weight: 700; letter-spacing: -0.4px;">
                                MFA Verification Code
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td class="mobile-padding" style="padding: 0 32px 30px 32px;">
                            <p style="margin: 0 0 14px 0; font-size: 15px; color: #1E2F3F;">
                                <strong style="color: #A43734;">{{ strtoupper($resolvedName) }}</strong>,
                            </p>

                            <p style="margin: 0 0 12px 0; font-size: 14px; color: #4A627A;">
                                Use the following OTP to complete the procedure to change your email address.
                                OTP is valid for 5 minutes.
                            </p>

                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #1E2F3F; font-weight: 600;">
                                Your One-Time Password (OTP) for account verification is:
                            </p>

                            <div style="margin: 0 0 16px 0; text-align: center;">
                                <div
                                    style="display: inline-block; background: #F8FAFC; border: 1px solid #D8E3EA; border-radius: 12px; padding: 14px 26px; min-width: 170px;">
                                    <span
                                        style="font-size: 30px; font-weight: 700; letter-spacing: 6px; color: #0F4F75;">{{ $resolvedOtp }}</span>
                                </div>
                            </div>

                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #4A627A;">
                                Please do not share this code with anyone. If you didn't request this code, please
                                ignore this email.
                            </p>

                            <p style="margin: 0; font-size: 13px; color: #4A627A;">
                                For any inquiries, please contact our support team at
                                <a href="mailto:support@Jetze.pk" style="color: #0F4F75; text-decoration: none;">support@Jetze.pk</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 18px 28px; background: #F8FAFC; border-top: 1px solid #D8E3EA; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #5A6E7F;">
                                © 2026 Jetze. All rights reserved.
                            </p>
                            <p style="margin: 6px 0 0 0; font-size: 12px; color: #5A6E7F;">
                                This is an automated email. Please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
