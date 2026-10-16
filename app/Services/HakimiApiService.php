<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HakimiApiService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => (int) config('hakimi.timeout'),
            'connect_timeout' => 10,
        ]);
    }

    /** Search Hakimi's test-only fixed-departure inventory. */
    public function searchFlights(array $params): array
    {
        $apiKey = config('hakimi.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('Hakimi test API is not configured.');
        }

        $payload = [
            'origin' => strtoupper((string) $params['origin']),
            'destination' => strtoupper((string) $params['destination']),
            'date' => $params['departure_date'],
            'adults' => (int) ($params['adults'] ?? 1),
            'children' => (int) ($params['children'] ?? 0),
            'infants' => (int) ($params['infants'] ?? 0),
        ];
        $url = config('hakimi.base_url') . '/flights/search';

        // Keep integration troubleshooting in Laravel's normal log, but never
        // write the secret test key to disk.
        Log::info('Hakimi test flight search request.', [
            'url' => $url,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => '[redacted]',
            ],
            'payload' => $payload,
        ]);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
                'json' => $payload,
            ]);
        } catch (RequestException $exception) {
            $errorResponse = $exception->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : null;

            Log::warning('Hakimi test flight search failed.', [
                'status' => $errorResponse?->getStatusCode(),
                'origin' => $payload['origin'],
                'destination' => $payload['destination'],
                'date' => $payload['date'],
                'response' => $this->decodeResponse($errorBody),
            ]);

            throw $exception;
        } catch (GuzzleException $exception) {
            Log::warning('Hakimi test flight search connection failed.', [
                'origin' => $payload['origin'],
                'destination' => $payload['destination'],
                'date' => $payload['date'],
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $data = $this->decodeResponse((string) $response->getBody());

        Log::info('Hakimi test flight search response.', [
            'status' => $response->getStatusCode(),
            'response' => $data,
        ]);

        if (!is_array($data) || !($data['success'] ?? false)) {
            Log::warning('Hakimi test flight search returned an unsuccessful payload.', [
                'status' => $response->getStatusCode(),
                'response' => $data,
            ]);

            throw new RuntimeException('Hakimi returned an invalid search response.');
        }

        return $data;
    }

    /**
     * Confirm the total payable amount immediately before checkout. Hakimi does
     * not accept ancillary selections on this endpoint.
     */
    public function reprice(string $inventoryId, array $passengerCounts): array
    {
        $apiKey = config('hakimi.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('Hakimi test API is not configured.');
        }

        $payload = [
            'inventoryId' => $inventoryId,
            'adults' => (int) ($passengerCounts['ADT'] ?? $passengerCounts['adults'] ?? 1),
            'children' => (int) ($passengerCounts['CHD'] ?? $passengerCounts['children'] ?? 0),
            'infants' => (int) ($passengerCounts['INF'] ?? $passengerCounts['infants'] ?? 0),
        ];
        $url = config('hakimi.base_url') . '/flights/reprice';

        Log::info('Hakimi test flight reprice request.', [
            'url' => $url,
            'headers' => ['Content-Type' => 'application/json', 'x-api-key' => '[redacted]'],
            'payload' => $payload,
        ]);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
                'json' => $payload,
            ]);
        } catch (RequestException $exception) {
            $errorResponse = $exception->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : null;
            Log::warning('Hakimi test flight reprice failed.', [
                'status' => $errorResponse?->getStatusCode(),
                'inventory_id' => $inventoryId,
                'response' => $this->decodeResponse($errorBody),
            ]);
            throw $exception;
        } catch (GuzzleException $exception) {
            Log::warning('Hakimi test flight reprice connection failed.', [
                'inventory_id' => $inventoryId,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $data = $this->decodeResponse((string) $response->getBody());
        Log::info('Hakimi test flight reprice response.', [
            'status' => $response->getStatusCode(),
            'response' => $data,
        ]);

        if (!is_array($data) || !($data['success'] ?? false) || !isset($data['totalFare'])) {
            Log::warning('Hakimi test flight reprice returned an unsuccessful payload.', [
                'status' => $response->getStatusCode(),
                'response' => $data,
            ]);
            throw new RuntimeException('Hakimi returned an invalid reprice response.');
        }

        return $data;
    }

    /** Create a test booking after the final Reprice check. */
    public function book(string $inventoryId, array $passengers, array $contact): array
    {
        $apiKey = config('hakimi.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('Hakimi test API is not configured.');
        }

        $payload = [
            'inventoryId' => $inventoryId,
            'passengers' => $passengers,
            'contact' => $contact,
        ];
        $url = config('hakimi.base_url') . '/bookings';

        // Do not log names, contact details, passport numbers, or the API key.
        Log::info('Hakimi test booking request.', [
            'url' => $url,
            'headers' => ['Content-Type' => 'application/json', 'x-api-key' => '[redacted]'],
            'inventory_id' => $inventoryId,
            'passenger_types' => array_values(array_map(fn (array $passenger) => $passenger['type'] ?? 'unknown', $passengers)),
            'passenger_count' => count($passengers),
        ]);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
                'json' => $payload,
            ]);
        } catch (RequestException $exception) {
            $errorResponse = $exception->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : null;
            Log::warning('Hakimi test booking failed.', [
                'status' => $errorResponse?->getStatusCode(),
                'inventory_id' => $inventoryId,
                'response' => $this->decodeResponse($errorBody),
            ]);
            throw $exception;
        } catch (GuzzleException $exception) {
            Log::warning('Hakimi test booking connection failed.', [
                'inventory_id' => $inventoryId,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $data = $this->decodeResponse((string) $response->getBody());
        Log::info($data);

        if (!is_array($data) || !($data['success'] ?? false) || blank($data['hakimiBookingNo'] ?? null)) {
            throw new RuntimeException('Hakimi returned an invalid booking response.');
        }

        return $data;
    }

    /** Fetch the current status and details for an existing Hakimi booking. */
    public function bookingDetails(string $hakimiBookingNo): array
    {
        $apiKey = config('hakimi.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('Hakimi test API is not configured.');
        }

        $bookingNumber = trim($hakimiBookingNo);
        if ($bookingNumber === '') {
            throw new RuntimeException('A Hakimi booking number is required.');
        }

        $url = config('hakimi.base_url').'/bookings/'.rawurlencode($bookingNumber);

        Log::info('Hakimi test booking-details request.', [
            'url' => $url,
            'headers' => ['Accept' => 'application/json', 'x-api-key' => '[redacted]'],
            'hakimi_booking_no' => $bookingNumber,
        ]);

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
            ]);
        } catch (RequestException $exception) {
            $errorResponse = $exception->getResponse();

            Log::warning('Hakimi test booking-details request failed.', [
                'status' => $errorResponse?->getStatusCode(),
                'hakimi_booking_no' => $bookingNumber,
                'response' => $this->decodeResponse($errorResponse ? (string) $errorResponse->getBody() : null),
            ]);

            throw $exception;
        } catch (GuzzleException $exception) {
            Log::warning('Hakimi test booking-details connection failed.', [
                'hakimi_booking_no' => $bookingNumber,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $data = $this->decodeResponse((string) $response->getBody());

        // Do not log traveller/contact records returned by this endpoint.
        Log::info('Hakimi test booking-details response.', [
            'status' => $response->getStatusCode(),
            'success' => $data['success'] ?? false,
            'hakimi_booking_no' => $data['hakimiBookingNo'] ?? $bookingNumber,
            'booking_status' => $data['status'] ?? null,
            'total_fare' => $data['totalFare'] ?? null,
            'currency' => $data['currency'] ?? null,
            'test_booking' => $data['testBooking'] ?? null,
        ]);

        if (! is_array($data) || ! ($data['success'] ?? false)) {
            throw new RuntimeException('Hakimi returned an invalid booking-details response.');
        }

        return $data;
    }

    private function decodeResponse(?string $body): mixed
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }
}
