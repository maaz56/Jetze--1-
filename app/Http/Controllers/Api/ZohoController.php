<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingInvoice;
use App\Models\FlightBookings;
use App\Models\FlightPassenger;
use App\Models\Passenger;
use App\Models\ZohoCredentials;
use App\Services\ZohoService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Log;

class ZohoController extends Controller
{
    protected $zohoService;

    public function __construct()
    {
        $this->zohoService = new ZohoService();
    }

    public function store(Request $request)
    {
        // Validate the request data
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            // 'redirect_uri' => 'required|url',
        ]);

        $existing = ZohoCredentials::first();
        if ($existing) {
            $existing->update([
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
            ]);
        } else {
            ZohoCredentials::create([
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'redirect_uri' => config('zoho.redirect_uri'),
            ]);
        }

        return response()->json(['message' => 'Zoho API credentials saved successfully.']);
    }


    public function getToken(Request $request)
    {
        Log::info($request->all());
        $zoho = ZohoCredentials::first();
        $client_id = $zoho ? $zoho->client_id : null;
        $client_secret = $zoho ? $zoho->client_secret : null;

        $code = $request->input('code');
        if (!$code) {
            return response()->json(['message' => 'Authorization code is required.'], 400);
        }
        $response = $this->zohoService->getToken($code, $client_id, $client_secret);
        Log::info($response);
        $key = ZohoCredentials::first();
        if ($key) {
            $key->update([
                'access_token' => $response['access_token'] ?? null,
                'refresh_token' => $response['refresh_token'] ?? null,
                'expires_in' => $response['expires_in'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Zoho API token retrieved successfully.', 'data' => $response]);



    }

    public function getOrganization()
    {

        Log::info('Fetching Zoho organization details...');
        $organization = $this->zohoService->getOrganizationId();
        Log::info('Zoho organization response:', $organization);
        return response()->json(['data' => $organization]);
    }

    public function fetchOrCreateCustomer(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'name' => 'nullable|string',
        ]);

        // Prepare parameters for Zoho API
        $params = [
            'name' => $request->input('name') ?? 'Ali', // Zoho's "contact_name" is required for creation
            'email' => $request->input('email') ?? 'aliahmed@gmail.com',
            'phone' => $request->input('phone') ?? '1234567890',
            'country' => $request->input('country') ?? 'Pakistan',
        ];

        try {
            $customer = $this->zohoService->fetchOrCreateCustomer($params);

            Log::info('Zoho customer fetched or created:', $customer);

            return response()->json(['data' => $customer]);
        } catch (\Exception $e) {
            Log::error('Error fetching or creating Zoho customer: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching or creating customer.'], 500);
        }
    }

    public function createInvoice(Request $request)
    {
        $booking = FlightBookings::with('user.agentData')->find($request->input('booking_id'));
        Log::info($booking);
        $flightData = json_decode($booking->flight_data, true); // true → returns associative array
        $pnr_response = json_decode($booking->pnr_response, true); // true → returns associative array
        // Log::info($flightData );
        $itemName = 'Flight Booking';
        $itemDescription = '';
        $passenger = null;
        Log::info($flightData);
        // Check if flights exist
        if (
            (!empty($flightData['leg']['flights']) && is_array($flightData['leg']['flights'])) ||
            (!empty($flightData['original']['leg']['flights']) && is_array($flightData['original']['leg']['flights']))
        ) {
            $flights = $flightData['leg']['flights'] ?? $flightData['original']['leg']['flights'];
            $segments = [];

            foreach ($flights as $index => $flight) {
                $fromCity = $flight['from']['city']['name'] ?? '';
                $fromCode = $flight['from']['iata'] ?? '';
                $toCity = $flight['to']['city']['name'] ?? '';
                $toCode = $flight['to']['iata'] ?? '';

                $segments[] = "Flight " . ($index + 1) . ": {$fromCity} ({$fromCode}) → {$toCity} ({$toCode})";
            }

            // If more than 1, mark it as return booking
            if (count($segments) > 1) {
                $itemName = 'Flight Booking - Return Trip';
            }

            // Join all segments with newline
            $itemDescription = implode("\n", $segments);

            $passenger = FlightPassenger::where('booking_id', $booking->id)
                ->whereIn('type', ['ADULT', 'adult', 'ADT'])
                ->first();
        }

        $params = [
            'customer_name' => $booking->user->agentData->ceo_name . ' (' . $booking->user->agentData->agent_uid . ')',
            'customer_email' => $booking->user->email,
            'customer_phone' => $booking->user->agentData->phone,
            'company_name' => $booking->user->agentData->company_name,
            'customer_country' => $booking->main_country,
            'item_name' => $itemName,
            'item_description' => $itemDescription,
            'amount' => $booking->amount,
            'currency_code' => $flightData['leg']['flights'][0]['fares'][0]['currency']['code'] ?? $flightData['original']['leg']['flights'][0]['fares'][0]['currency']['code'],
            'currency_symbol' => $flightData['leg']['flights'][0]['fares'][0]['currency']['symbol'] ?? $flightData['original']['leg']['flights'][0]['fares'][0]['currency']['symbol'],
        ];

        try {
            $invoice = $this->zohoService->createInvoice($params);
            $payment = $this->zohoService->createPayment($invoice);
            Log::info('Zoho invoice created:', $invoice);

            BookingInvoice::create([
                'booking_id' => $booking->id,
                'invoice_id' => $invoice['invoice_id'],
                'amount' => $booking->amount,
                'currency_code' => $invoice['currency_code'],
                'invoice_url' => $invoice['invoice_url'] ?? null,
            ]);
            return response()->json(['data' => $invoice]);
        } catch (\Exception $e) {
            Log::error('Error creating Zoho invoice: ' . $e->getMessage());
            return response()->json(['message' => 'Error creating invoice.'], 500);
        }
    }


    public function getKeys()
    {
        $key = ZohoCredentials::first();

        return response()->json(['data' => $key]);
    }
}
