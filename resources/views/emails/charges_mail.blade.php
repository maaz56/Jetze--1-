<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charges Applied | Jetze.pk</title>
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
            .amount-card {
                text-align: center !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F0F2F5; color: #1A2C3E; line-height: 1.5;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td style="padding: 40px 20px; background-color: #F0F2F5;">
                    <table align="center" cellpadding="0" cellspacing="0" width="620" class="responsive-table"
                        style="border-collapse: collapse; background-color: #FFFFFF; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.12); margin: 0 auto;">

                        <!-- Brand Header with accent stripe -->
                        <tr>
                            <td style="padding: 0;">
                                <div style="background: linear-gradient(90deg, #0A2E4D 0%, #1B4A6F 50%, #C9A03D 100%); height: 5px;"></div>
                                <div style="padding: 32px 32px 20px 32px; text-align: center; background: #FFFFFF;">
                                    <img src="{{ asset('assets/logo.png') }}" alt="Jetze" width="165"
                                        style="height: auto; display: inline-block;">
                                </div>
                             </td>
                         </tr>

                        <!-- Hero Section -->
                         <tr>
                            <td style="padding: 0 32px 20px 32px; text-align: center;">
                                <div style="background: #FEF8E7; display: inline-block; padding: 6px 16px; border-radius: 40px; margin-bottom: 18px;">
                                    <span style="font-size: 12px; font-weight: 600; color: #C9A03D; letter-spacing: 0.5px;">⚠️ ACCOUNT UPDATE</span>
                                </div>
                                <h1 style="margin: 0 0 10px 0; font-size: 34px; line-height: 1.2; color: #0A2E4D; font-weight: 700; letter-spacing: -0.5px;">
                                    Charges Applied
                                </h1>
                                <p style="margin: 0; font-size: 16px; color: #5A6E7F;">New charges have been applied to your Jetze account</p>
                            </td>
                        </tr>

                        <!-- Main Content -->
                        <tr>
                            <td style="padding: 0 32px 32px 32px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                    style="border-collapse: collapse;">

                                    <!-- Greeting -->
                                    <tr>
                                        <td style="padding-bottom: 20px;">
                                            <p style="margin: 0 0 6px 0; font-size: 18px; font-weight: 600; color: #1E2F3F;">
                                                Hello <span style="color: #C9A03D; font-weight: 700;">{{ $charge->agent->email ?? 'Valued Customer' }}</span>,
                                            </p>
                                            <p style="margin: 0; font-size: 15px; color: #4A627A;">
                                                We want to inform you that new charges have been applied to your account. Please review the details below:
                                            </p>
                                        </td>
                                    </tr>

                                    <!-- Charge Details Card -->
                                    <tr>
                                        <td style="padding-bottom: 28px;">
                                            <div style="background: #F8FBFE; border-radius: 24px; border: 1px solid #E8EDF2; overflow: hidden;">
                                                
                                                <!-- Payment Type Header -->
                                                <div style="padding: 18px 24px; background: linear-gradient(135deg, #FAFCFE 0%, #FFFFFF 100%); border-bottom: 1px solid #EFF3F8;">
                                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                                        <tr>
                                                            <td style="vertical-align: middle;">
                                                                <span style="display: inline-block; width: 10px; height: 10px; background: #C9A03D; border-radius: 50%; margin-right: 10px;"></span>
                                                                <span style="font-weight: 700; font-size: 16px; color: #1E2F3F;">Payment Details</span>
                                                            </td>
                                                            <td style="text-align: right; vertical-align: middle;">
                                                                <span style="font-size: 12px; color: #6F8FAA; background: #F0F3F8; padding: 4px 12px; border-radius: 20px;">{{ date('F j, Y') }}</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>

                                                <!-- Charge Information -->
                                                <div style="padding: 24px 24px 20px 24px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                                        <tr>
                                                            <td style="padding-bottom: 18px;">
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td style="width: 40%;">
                                                                            <span style="font-size: 13px; color: #6C869C; text-transform: uppercase; letter-spacing: 0.5px;">Payment Type</span>
                                                                        </td>
                                                                        <td style="width: 60%;">
                                                                            <span style="font-size: 16px; font-weight: 600; color: #1E2F3F;">{{ $charge->payment_type ?? 'Standard Charge' }}</span>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding-bottom: 18px;">
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td style="width: 40%;">
                                                                            <span style="font-size: 13px; color: #6C869C; text-transform: uppercase; letter-spacing: 0.5px;">Charge Description</span>
                                                                        </td>
                                                                        <td style="width: 60%;">
                                                                            <span style="font-size: 15px; color: #2C4A6E;">{{ $charge->additional_details ?? 'Service charge applied to your account' }}</span>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 12px 0 0 0;">
                                                                <div style="background: #FEFCF7; border-radius: 20px; padding: 20px; text-align: center; border: 1px solid #F5EFE0;">
                                                                    <span style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #C9A03D; font-weight: 600;">Amount Charged</span>
                                                                    <p style="margin: 12px 0 0 0; font-size: 42px; font-weight: 800; color: #0A2E4D; letter-spacing: -1px;">
                                                                        PKR {{ number_format($charge->amount ?? 0, 2) }}
                                                                    </p>
                                                                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #8FA5B8;">Inclusive of all taxes and fees</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Transaction Summary -->
                                    @php
                                        $chargeId = $charge->id ?? 'CH-' . strtoupper(substr(uniqid(), -8));
                                        $status = $charge->status ?? 'Processed';
                                    @endphp
                                    <tr>
                                        <td style="padding-bottom: 24px;">
                                            <div style="background: #F9FCFE; border-radius: 20px; padding: 16px 20px; border: 1px solid #E8EDF2;">
                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="width: 50%;">
                                                            <span style="font-size: 12px; color: #8FA0B0;">Transaction ID</span>
                                                            <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 600; color: #0A2E4D;">{{ $chargeId }}</p>
                                                        </td>
                                                        <td style="width: 50%; text-align: right;">
                                                            <span style="font-size: 12px; color: #8FA0B0;">Status</span>
                                                            <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 600; color: #2C8F6E;">✓ {{ $status }}</p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Action Message -->
                                    <tr>
                                        <td style="padding-bottom: 20px;">
                                            <div style="background: #EFF6FF; border-radius: 16px; padding: 16px 20px; border-left: 4px solid #C9A03D;">
                                                <p style="margin: 0; font-size: 14px; color: #1E2F3F;">
                                                    <strong>📋 Next Steps:</strong> Please review your account dashboard for detailed transaction history. If you have any questions about this charge, our support team is ready to assist you.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>

                                   

                                    <!-- Support Message -->
                                    <tr>
                                        <td style="padding-top: 8px;">
                                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #4F6F8F; line-height: 1.5;">
                                                If you have any questions or believe this charge was applied in error, please don't hesitate to contact our support team. We're here to help you 24/7.
                                            </p>
                                            
                                            <div style="margin-top: 24px; padding: 20px 0 0 0; border-top: 1px solid #E9EFF4;">
                                                <p style="margin: 0; font-size: 15px; font-weight: 500; color: #0A2E4D;">
                                                    Thank you for your continued trust in Jetze,<br>
                                                    <span style="font-size: 13px; font-weight: 400; color: #6F8FAA;">Finance & Support Team</span>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>

                                </table>
                            </td>
                        </tr>

                        <!-- Footer - Elegant & Professional -->
                        <tr>
                            <td style="background-color: #F9FBFD; border-top: 1px solid #E9EFF4; padding: 28px 32px 32px 32px; text-align: center;">

                                <!-- Social Links -->
                                <table role="presentation" cellpadding="0" cellspacing="0"
                            style="border-collapse: collapse; margin: 0 auto 20px auto;">
                            <tr>
                                <td style="padding: 0 8px;">
                                    <a href="#">
                                        <table role="presentation" width="38" height="38"
                                            style="background-color: #0A2E4D; border-radius: 50%;">
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
                                            style="background-color: #0A2E4D; border-radius: 50%;">
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
                                            style="background-color: #C9A03D; border-radius: 50%;">
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
                                            style="background-color: #0A2E4D; border-radius: 50%;">
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
                                    📞 24/7 Support: +92 00000000 &nbsp;|&nbsp; 
                                    ✉️ <a href="mailto:support@Jetze.pk" style="color: #0A2E4D; text-decoration: none;">support@Jetze.pk</a>
                                </p>

                                <div style="margin: 16px 0 12px 0;">
                                    <a href="#" style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Privacy Policy</a>
                                    <span style="color: #DAE2EA;">|</span>
                                    <a href="#" style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Terms of Service</a>
                                    <span style="color: #DAE2EA;">|</span>
                                    <a href="#" style="color: #8EA3B8; text-decoration: none; font-size: 12px; margin: 0 10px;">Unsubscribe</a>
                                </div>

                                <p style="margin: 20px 0 0 0; font-size: 11px; color: #9AB0C2;">
                                    © {{ date('Y') }} Jetze. All rights reserved. Your trusted travel partner.
                                </p>
                                <p style="margin: 12px 0 0 0; font-size: 10px; color: #B2C4D4;">
                                    This is a transactional email regarding charges applied to your account. Please keep for your records.
                                </p>
                            </td>
                        </tr>

                    </table>
                    <div style="height: 8px;">&nbsp;</div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>