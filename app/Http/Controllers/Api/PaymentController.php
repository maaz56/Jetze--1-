<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DepositData;
use App\Models\FlightBookings;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;
use Str;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{

    protected $abhiPayBaseURl;
    protected $abhiPayInvoiceURl;
    protected $abhiPaySecretKey;
    protected $abhiPayMerchant;
    protected $abhiPayInvoiceSecretKey;
    public function __construct()
    {
        $this->abhiPayBaseURl = env(
            'ABHIPAY_URL',
            'https://api.abhipay.com.pk/api/v3/orders'
        );

        $this->abhiPaySecretKey = env(
            'ABHIPAY_SECRET_KEY',
            '2A379B30219140B0B8676267F3A3F093'
        );
        $this->abhiPayMerchant = env(
            'ABHIPAY_MERCHANT_ID',
            'ES1091578'
        );
        $this->abhiPayInvoiceURl = env(
            'ABHIPAY_INVOICE_URL',
            'https://api.abhipay.com.pk/api/v2'

        );
        $this->abhiPayInvoiceSecretKey = env(
            'ABHIPAY_INVOICE_SECRET_KEY',
            '1290F54BD5A04E3980A478D565F88773'
        );
    }
    public function createIntent(Request $request)
    {
        Log::info(config('services.stripe.secret'));
        Stripe::setApiKey(config('services.stripe.secret'));
        Log::info($request);
        $amount = $request->amount;

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'aed',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        return response()->json([
            'clientSecret' => $intent->client_secret,
        ]);
    }

    public function initializeAbhiPay(Request $request)
    {
        if ($request->paymentMethod === 'abhipay-bank') {
            return $this->initializeAbhiPayBank($request);
        } else if ($request->paymentMethod === 'abhipay') {
            return $this->initializeAbhiPayCard($request);
        } else if ($request->paymentMethod === 'abhipay-deposit' || $request->payment_type === 'abhipay-deposit') {
            return $this->initializeAbhiPayDeposit($request);
        }
    }
    public function initializeAbhiPayDeposit(Request $request)
    {
        Log::info('AbhiPay Deposit Init Request:', $request->all());
        $user = Auth::user();
        $customer = Customer::where('user_id', $user->id)->first();
        Log::info($customer);
        $tid = Str::random(10) . time();
        $depositDataController = new DepositDataController();
        $request->merge([
            'amount' => (int) $request->amount + 500,
        ]);

        $depositData = $depositDataController->store($request);
        $depositData = json_decode($depositData->getContent(), true);
        Log::info('Deposit created:', $depositData['deposit']);
        $deposit_id = $depositData['deposit']['id'];
        $deposit = DepositData::find($deposit_id);

        if (!$deposit) {
            return response()->json([
                'success' => false,
                'message' => 'deposit not found'
            ], 404);
        }
        $deposit->t_id = $tid;
        $deposit->t_status = 'pending';
        $deposit->save();
        $linkExpirationDate = now('Asia/Karachi')
            ->addYear()
            ->addMinutes(20)
            ->format('Y-m-d\TH:i:s.000');
        Log::info(Carbon::now('Asia/Karachi'));
        $payload = [
            "amount" => (int) $request->amount,
            "merchantId" => $this->abhiPayMerchant,
            "currency" => $request->currency ?? "PKR",
            "firstName" => $customer->name,
            "lastName" => $customer->last_name ?? " ",
            "description" => "Flight deposit #" . $deposit->id,
            "linkExpirationDate" => $linkExpirationDate,
            "approveURL" => "",
            "cancelURL" => "",
            "declineURL" => "",
            "clientUuid" =>
                $tid,


        ];
        $body = [
            "merchant" => $this->abhiPayMerchant,
            "body" => $payload
        ];

        Log::info('AbhiPay Bank Payload:', $body);
        Log::info($this->abhiPayInvoiceURl);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->send(new \GuzzleHttp\Psr7\Request(
                'POST',
                $this->abhiPayInvoiceURl . '/invoices',
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->abhiPayInvoiceSecretKey
                ],
                json_encode($body)
            ));
            $responseBody = json_decode($response->getBody(), true);
            //             $responseBody = '{"code":"00000","message":"Operation performed successfully","route":"dashboard/main","internalMessage":null,"responseId":"5d231445c0ee45f5a3f2e5ebdfb64ba1","payload":{"id":680945,"consumerNumber":"1000292480680945","customer":{"id":685249,"fullName":"Arslan ","firstName":"Arslan","lastName":" "},"merchantId":"ES1091578","amount":160,"expireDate":"2026-03-13T22:57:58","fullName":"Arslan  ","firstName":"Arslan","lastName":" ","currencyType":"PKR","status":"ACTIVE","billStatus":"UNPAID","description":"Flight deposit #30","invoiceStatus":"PENDING","appName":"ETIMAD TRAVELS - 1Link","uuid":"R09I7","invoiceUuid":"29a05fa83f5c44e885d53680bcca5dae","invoiceCode":"1000292480680945","linkExpirationDate":"2026-03-13T22:57:58","createdDate":"2026-03-13T22:37:59.581335614","previewUrl":"https://pay.abhipay.com.pk/r/R09I7","approveURL":"","cancelURL":"","declineURL":"","env":"PROD","clientUuid":"ZojSp7cM9s1773423478"}} 
// ';
// $responseBody = json_decode($responseBody, true);
            Log::info('AbhiPay Bank Response:', $responseBody);
            if (isset($responseBody['payload']['consumerNumber'])) {
                $deposit->t_id = $responseBody['payload']['clientUuid'];
                $deposit->invoice_id = $responseBody['payload']['consumerNumber'];
                $deposit->save();

            } else {
                Log::error('AbhiPay payload missing', $responseBody);
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice creation failed',
                    'response' => $responseBody
                ], 400);
            }
            $deposit->save();
            return response()->json([
                'success' => true,
                'response' => $responseBody
            ]);

        } catch (\Exception $e) {

            Log::error('AbhiPay Init Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Initialization failed'
            ], 500);
        }


    }
    public function initializeAbhiPayBank(Request $request)
    {
        Log::info('AbhiPay Bank Init Request:', $request->all());
        $user = Auth::user();
        $customer = Customer::where('user_id', $user->id)->first();
        Log::info($customer);
        $tid = Str::random(10) . time();
        $booking = FlightBookings::find($request->booking_id);
        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }
        $booking->tid = $tid;
        $booking->t_status = 'pending';
        $booking->save();
        $linkExpirationDate = now('Asia/Karachi')
            ->addHours(4)
            ->addMinutes(20)
            ->format('Y-m-d\TH:i:s.000');
        $payload = [
            "amount" => (int) $request->amount + 500,
            "merchantId" => $this->abhiPayMerchant,
            "currency" => $request->currency ?? "PKR",
            "firstName" => $customer->name,
            "lastName" => $customer->last_name ?? " ",
            "description" => "Flight Booking #" . $request->booking_id,
            "linkExpirationDate" => $linkExpirationDate,
            "approveURL" => "",
            "cancelURL" => "",
            "declineURL" => "",
            "clientUuid" =>
                $tid,


        ];
        $body = [
            "merchant" => $this->abhiPayMerchant,
            "body" => $payload
        ];

        Log::info('AbhiPay Bank Payload:', $body);
        Log::info($this->abhiPayInvoiceURl);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->send(new \GuzzleHttp\Psr7\Request(
                'POST',
                $this->abhiPayInvoiceURl . '/invoices',
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->abhiPayInvoiceSecretKey
                ],
                json_encode($body)
            ));
            $responseBody = json_decode($response->getBody(), true);
            Log::info('AbhiPay Bank Response:', $responseBody);
            if (isset($responseBody['payload']['consumerNumber'])) {
                $booking->tid = $responseBody['payload']['clientUuid'];
                $booking->invoice_id = $responseBody['payload']['consumerNumber'];
                $booking->save();

            } else {
                Log::error('AbhiPay payload missing', $responseBody);
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice creation failed',
                    'response' => $responseBody
                ], 400);
            }
            $booking->save();
            return response()->json([
                'success' => true,
                'response' => $responseBody
            ]);

        } catch (\Exception $e) {

            Log::error('AbhiPay Init Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Initialization failed'
            ], 500);
        }


    }
    public function initializeAbhiPayCard(Request $request)
    {


        Log::info('AbhiPay Init Request:', $request->all());

        $tid = Str::random(10) . time();
        $booking = FlightBookings::find($request->booking_id);
        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }
        $booking->tid = $tid;
        $booking->t_status = 'pending';
        $booking->save();

        // 🔹 Build AbhiPay Payload
        $payload = [
            "amount" => (int) $request->amount,
            "language" => "EN",
            "currency" => $request->currency ?? "PKR",
            "description" => "Flight Booking #" . $request->booking_id,
            "callbackUrl" => $request->callback_url,
            "cardSave" => true,
            "operation" => "PURCHASE",

            // 🔹 Unique client transaction id
            "clientTransactionId" =>
                $tid,

            // 🔹 Optional
            "excludedDiscountAmount" => "0"
        ];

        Log::info('AbhiPay Payload:', $payload);
        Log::info($this->abhiPayBaseURl);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->send(new \GuzzleHttp\Psr7\Request(
                'POST',
                $this->abhiPayBaseURl,
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->abhiPaySecretKey
                ],
                json_encode($payload)
            ));
            $responseBody = json_decode($response->getBody(), true);
            Log::info('AbhiPay Response:', $responseBody);
            return response()->json([
                'success' => true,
                'response' => $responseBody
            ]);

        } catch (\Exception $e) {

            Log::error('AbhiPay Init Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Initialization failed'
            ], 500);
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        if ($request->paymentMethod === 'abhipay-bank') {
            return $this->checkPaymentStatusBank($request);
        } else if ($request->paymentMethod === 'abhipay') {
            return $this->checkPaymentStatusCard($request);
        } else if ($request->paymentMethod === 'abhipay-deposit' || $request->payment_type === 'abhipay-deposit') {
            return $this->checkPaymentStatusDeposit($request);
        }
    }
    public function checkPaymentStatusCard(Request $request)
    {
        $bookingId = $request->booking_id;
        $booking = FlightBookings::where('id', $bookingId)->first();
        $transactionId = $booking->tid;
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->send(new \GuzzleHttp\Psr7\Request(
                'GET',
                "{$this->abhiPayBaseURl}/by-rrn/{$transactionId}",
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->abhiPaySecretKey
                ],
            ));
            $responseBody = json_decode($response->getBody(), true);
            //             $responseBody = '{
//     "code": "00000",
//     "message": "Operation performed successfully",
//     "route": "dashboard/main",
//     "internalMessage": null,
//     "responseId": "76bb5408605a431aa26abb504d8ffb2d",
//     "payload": {
//         "orderId": "4f5467dc-2746-4379-8da3-715ed1db28ff",
//         "sessionId": "4f5467dc-2746-4379-8da3-715ed1db28ff",
//         "amount": 10.00,
//         "currencyType": "PKR",
//         "merchantName": "ETIMAD TRAVELS - Card Gateway ",
//         "commission": null,
//         "commissionRate": 2.3000,
//         "paymentStatus": "APPROVED",
//         "auto": false,
//         "createdDate": "2026-02-18T06:40:17.200577",
//         "description": "string",
//         "transactions": [
//             {
//                 "uuid": "2aa10ea0-8d79-411c-8fbc-e25ce01933b9",
//                 "createdDate": "2026-02-18T06:41:13.417492",
//                 "status": "APPROVED",
//                 "responseDescription": null,
//                 "channel": "NEEZAM_BANK",
//                 "requestRrn": "4545217",
//                 "responseRrn": "cf641e05-2a49-456b-8813-659a4c262c2d",
//                 "cardDetails": {
//                     "cardHolderName": "Muaz Ahmed",
//                     "maskedPan": "559049******6882",
//                     "brand": "MASTER_CARD",
//                     "uuid": null,
//                     "bankName": "SadaPay"
//                 },
//                 "cardUuid": "e3d17ff0-90ea-4447-b6ea-393ce9ebd0f4",
//                 "recurrenceId": null,
//                 "threeDSData": null
//             }
//         ],
//         "orgSessionId": "cf641e05-2a49-456b-8813-659a4c262c2d",
//         "bankName": "SadaPay",
//         "initialAmount": 10.00,
//         "discountRate": null,
//         "discountedAmount": null,
//         "excludedDiscountAmount": 20.00,
//         "taxRate": null,
//         "calculatedTax": null,
//         "amountAfterDiscount": 10.00,
//         "discounted": false,
//         "clientTransactionId": "1234abcdechcfgvjbeckohd1567"
//     }
// }';
// $responseBody = json_decode($responseBody, true);

            Log::info('AbhiPay payment status response:', $responseBody['payload']);
            if ($responseBody['payload']['paymentStatus'] === 'APPROVED' && $responseBody['code'] === '00000') {
                $booking->t_status = 'approved';
                $booking->save();
                return response()->json([
                    'success' => true,
                    'response' => $responseBody['payload']['paymentStatus']
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not approved',
                    'details' => $responseBody['payload']
                ], 200);
            }

        } catch (\Exception $e) {

            Log::error('AbhiPay Init Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Initialization failed'
            ], 500);
        }

    }
    public function checkPaymentStatusBank(Request $request)
    {
        $bookingId = $request->booking_id;
        $booking = FlightBookings::where('id', $bookingId)->first();

        $payload = [
            "merchant" => $this->abhiPayMerchant,
            "body" => [
                "clientUuid" => $booking->tid,
            ]
        ];
        $payload = json_encode($payload);
        Log::info($payload);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->abhiPayInvoiceURl . '/get-invoice', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => trim($this->abhiPayInvoiceSecretKey)
                ],
                'json' => [
                    "merchant" => trim($this->abhiPayMerchant),
                    "body" => [
                        "clientUuid" => trim($booking->tid)
                    ]
                ]
            ]);
            $responseBody = json_decode($response->getBody(), true);
            //                          $responseBody = '{
//     "code": "00000",
//     "message": "Operation performed successfully",
//     "route": "dashboard/main",
//     "internalMessage": null,
//     "responseId": "01a16fcbf7e44c91be3cb0a5c9ffe3c6",
//     "payload": {
//         "id": 660104,
//         "consumerNumber": "1000292480660104",
//         "customer": null,
//         "merchantId": "ES1091578",
//         "amount": 10.00,
//         "amountWithFee": null,
//         "paidAmount": 10.00,
//         "surcharge": null,
//         "expireDate": "2030-12-06T20:54:41.629",
//         "currencyType": "PKR",
//         "languageType": null,
//         "status": "ACTIVE",
//         "billStatus": "PAID",
//         "active": true,
//         "description": "testing",
//         "uuid": "R0Saw",
//         "invoiceUuid": "52bed009e49148b68c7225f111c19a54",
//         "clientUuid": "abcdefghijk",
//         "invoiceCode": "1000292480660104",
//         "previewUrl": "https://pay.abhipay.com.pk/r/R0Saw",
//         "invoiceStatus": "COMPLETE",
//         "source": "ONELINK",
//         "createdDate": "2026-02-18T18:56:24.704107",
//         "lastModifiedDate": null,
//         "tranAuthId": "049723",
//         "env": "PROD",
//         "approveURL": "www.google.com",
//         "cancelURL": "www.youtube.com",
//         "declineURL": "www.facebook.com",
//         "transaction": {
//             "sessionId": null,
//             "paymentStatus": "APPROVED",
//             "transactionApproveDate": "2026-02-18 18:58:39",
//             "settlementDate": null
//         }
//     }
// }';
// $responseBody = json_decode($responseBody, true);

            Log::info('AbhiPay payment status response:', $responseBody);
            if ($responseBody['payload']['billStatus'] === 'PAID' && $responseBody['code'] === '00000') {
                $booking->t_status = 'approved';
                $booking->save();
                return response()->json([
                    'success' => true,
                    'response' => $responseBody['payload']['billStatus']
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not approved',
                    'details' => $responseBody['payload']
                ], 200);
            }

        } catch (\Exception $e) {

            Log::error('AbhiPay Init Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }

    }
    public function checkPaymentStatusDeposit(Request $request)
    {
        Log::info('Checking AbhiPay Deposit Status:', $request->all());
        $depositId = $request->deposit_id;
        $deposit = DepositData::where('id', $depositId)->first();

        $payload = [
            "merchant" => $this->abhiPayMerchant,
            "body" => [
                "clientUuid" => $deposit->t_id,
            ]
        ];
        $payload = json_encode($payload);
        Log::info($payload);
        Log::info($this->abhiPayInvoiceURl . '/get-invoice');
        Log::info($this->abhiPayInvoiceSecretKey);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->abhiPayInvoiceURl . '/get-invoice', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => trim($this->abhiPayInvoiceSecretKey)
                ],
                'json' => [
                    "merchant" => trim($this->abhiPayMerchant),
                    "body" => [
                        "clientUuid" => trim($deposit->t_id)
                    ]
                ]
            ]);
            $responseBody = json_decode($response->getBody(), true);
            //                          $responseBody = '{
//     "code": "00000",
//     "message": "Operation performed successfully",
//     "route": "dashboard/main",
//     "internalMessage": null,
//     "responseId": "01a16fcbf7e44c91be3cb0a5c9ffe3c6",
//     "payload": {
//         "id": 660104,
//         "consumerNumber": "1000292480660104",
//         "customer": null,
//         "merchantId": "ES1091578",
//         "amount": 10.00,
//         "amountWithFee": null,
//         "paidAmount": 10.00,
//         "surcharge": null,
//         "expireDate": "2030-12-06T20:54:41.629",
//         "currencyType": "PKR",
//         "languageType": null,
//         "status": "ACTIVE",
//         "billStatus": "PAID",
//         "active": true,
//         "description": "testing",
//         "uuid": "R0Saw",
//         "invoiceUuid": "52bed009e49148b68c7225f111c19a54",
//         "clientUuid": "abcdefghijk",
//         "invoiceCode": "1000292480660104",
//         "previewUrl": "https://pay.abhipay.com.pk/r/R0Saw",
//         "invoiceStatus": "COMPLETE",
//         "source": "ONELINK",
//         "createdDate": "2026-02-18T18:56:24.704107",
//         "lastModifiedDate": null,
//         "tranAuthId": "049723",
//         "env": "PROD",
//         "approveURL": "www.google.com",
//         "cancelURL": "www.youtube.com",
//         "declineURL": "www.facebook.com",
//         "transaction": {
//             "sessionId": null,
//             "paymentStatus": "APPROVED",
//             "transactionApproveDate": "2026-02-18 18:58:39",
//             "settlementDate": null
//         }
//     }
// }';
// $responseBody = json_decode($responseBody, true);

            Log::info('AbhiPay payment status response:', $responseBody);
            if ($responseBody['payload']['billStatus'] === 'PAID' && $responseBody['code'] === '00000') {
                $deposit->t_status = 'approved';
                $deposit->deposit_status = 'approved';
                $deposit->save();
                Log::info($deposit);
                return response()->json([
                    'success' => true,
                    'response' => $responseBody['payload']['billStatus'],
                    'deposit' => $deposit
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not approved',
                    'details' => $responseBody['payload']
                ], 200);
            }

        } catch (\Exception $e) {

            Log::error('AbhiPay Init Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }

    }




}
