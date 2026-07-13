<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message | Jetze.pk</title>
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
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:34px 14px;">
    <tr>
        <td align="center">
            <table role="presentation" width="620" cellpadding="0" cellspacing="0" class="email-shell"
                style="background:#FFFFFF;border-radius:26px;overflow:hidden;box-shadow:0 20px 36px rgba(10,46,77,0.16);">
                <tr>
                    <td style="padding:0;">
                        <div style="height:5px;background:#0A2E4D;"></div>
                        <div style="padding:30px 32px 20px 32px;text-align:center;">
                            <img src="{{ asset('assets/logo.png') }}" alt="Jetze" width="165"
                                style="display:inline-block;height:auto;">
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="px-mobile" style="padding:0 32px 30px 32px;">
                        <div
                            style="display:inline-block;padding:7px 16px;border-radius:40px;background:#EEF4FF;border:1px solid #D7E4F4;">
                            <span style="font-size:11px;font-weight:700;letter-spacing:.6px;color:#0A2E4D;">
                                CONTACT INQUIRY
                            </span>
                        </div>

                        <h1 style="margin:14px 0 10px 0;font-size:30px;line-height:1.2;color:#0A2E4D;font-weight:800;">
                            New Contact Message
                        </h1>
                        <p style="margin:0 0 18px 0;font-size:15px;color:#56708A;line-height:1.6;">
                            A new message was submitted from the Contact Us page.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border:1px solid #e5e7eb;border-radius:10px;margin-top:16px;">
                            <tr>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">Name</td>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                    {{ $payload['name'] }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">Email</td>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                    {{ $payload['email'] }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">Phone</td>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                    {{ $payload['phone'] }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">Subject</td>
                                <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#0A2E4D;font-weight:700;">
                                    {{ $payload['subject'] }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:13px 16px;font-size:13px;color:#6A8298;vertical-align:top;">Message</td>
                                <td style="padding:13px 16px;font-size:14px;color:#1D3347;line-height:1.6;">
                                    {!! nl2br(e($payload['message'])) !!}
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <tr>
                    <td class="px-mobile"
                        style="padding:22px 32px 28px 32px;border-top:1px solid #E9EFF4;background:#F9FBFD;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#7D93A8;">
                            This email was generated from Jetze.pk contact form.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>

</html>
