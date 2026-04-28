<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Request Received | Jetze</title>
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
        $agentName = $deposit->agent->agentData->company_name ?? $deposit->agent->name ?? 'Customer';
        $receiptRef = $deposit->receipt_reference ?? ('DEP-' . $deposit->id);
        $amount = number_format((float) ($deposit->amount ?? 0), 2);
        $currency = $deposit->currency ?? 'PKR';
        $paymentType = $deposit->payment_type ?? 'N/A';
        $invoiceId = $deposit->invoice_id ?? null;
        $paymentTypeNormalized = strtolower(trim((string) $paymentType));
        $paymentMethodLabel = $paymentTypeNormalized === 'cash' ? 'Cash' : 'Bank';
        $paymentDetailsLabel = $paymentTypeNormalized === 'cash'
            ? 'Cash'
            : ($paymentTypeNormalized === 'abhipay-deposit' ? 'AbhiPay Bank Transfer' : $paymentType);
        $requestDate = $deposit->date
            ? date('d M Y', strtotime((string) $deposit->date))
            : ($deposit->created_at ? $deposit->created_at->format('d M Y') : 'N/A');
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:34px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" class="email-shell"
                    style="background:#FFFFFF;border-radius:26px;overflow:hidden;box-shadow:0 20px 36px rgba(10,46,77,0.16);">
                    <tr>
                        <td style="padding:0;">
                            <div style="height:5px;background:linear-gradient(90deg,#0A2E4D 0%,#1B4A6F 50%,#C9A03D 100%);">
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
                                style="display:inline-block;padding:7px 16px;border-radius:40px;background:#FEF8E7;border:1px solid #F4E8C4;">
                                <span style="font-size:11px;font-weight:700;letter-spacing:.6px;color:#C9A03D;">
                                    REQUEST RECEIVED
                                </span>
                            </div>

                            <h1 style="margin:14px 0 10px 0;font-size:30px;line-height:1.2;color:#0A2E4D;font-weight:800;">
                                Your Deposit Request Has Been Received
                            </h1>
                            <p style="margin:0 0 18px 0;font-size:15px;color:#56708A;line-height:1.6;">
                                Dear <strong style="color:#0A2E4D;">{{ $agentName }}</strong>, we have received your
                                deposit request successfully.
                            </p>

                            <div
                                style="margin-bottom:18px;padding:14px 16px;border-radius:14px;background:#EFF6FF;border-left:4px solid #1B4A6F;">
                                <p style="margin:0;font-size:13px;color:#1E3C5A;line-height:1.55;">
                                    Your request is currently under verification. After review, we will approve it and
                                    inform you on your email.
                                </p>
                            </div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                style="border:1px solid #E7EEF5;border-radius:18px;overflow:hidden;background:#FFFFFF;">
                                <tr>
                                    <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">
                                        Reference
                                    </td>
                                    <td
                                        style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                        {{ $receiptRef }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">
                                        Amount
                                    </td>
                                    <td
                                        style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#0A2E4D;font-weight:700;">
                                        {{ $currency }} {{ $amount }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">
                                        Payment Method
                                    </td>
                                    <td
                                        style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                        {{ $paymentMethodLabel }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">
                                        Payment Details
                                    </td>
                                    <td
                                        style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                        {{ $paymentDetailsLabel }}
                                    </td>
                                </tr>
                                @if(!empty($invoiceId))
                                    <tr>
                                        <td style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:13px;color:#6A8298;">
                                            Invoice ID
                                        </td>
                                        <td
                                            style="padding:13px 16px;border-bottom:1px solid #EEF3F8;font-size:14px;color:#1D3347;font-weight:600;">
                                            {{ $invoiceId }}
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:13px 16px;font-size:13px;color:#6A8298;">
                                        Request Date
                                    </td>
                                    <td style="padding:13px 16px;font-size:14px;color:#1D3347;font-weight:600;">
                                        {{ $requestDate }}
                                    </td>
                                </tr>
                            </table>

                            <div style="padding-top:24px;">
                                <a href="{{ $loginUrl }}"
                                    style="display:inline-block;padding:12px 22px;border-radius:999px;background:#0A2E4D;color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:700;letter-spacing:.2px;">
                                    View Dashboard
                                </a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile"
                            style="padding:22px 32px 28px 32px;border-top:1px solid #E9EFF4;background:#F9FBFD;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#7D93A8;">
                                We appreciate your patience while our finance team verifies your request.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
