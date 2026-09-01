<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TboHotelService
{
    protected Client $client;
    protected ?string $baseUrl;
    protected ?string $username;
    protected ?string $password;
    protected int $searchTimeout;
    protected int $prebookTimeout;
    protected int $bookTimeout;
    protected int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('tbohotel.base_url'), '/');
        $this->username = config('tbohotel.username');
        $this->password = config('tbohotel.password');
        $this->searchTimeout = (int) config('tbohotel.timeout_search', 23);
        $this->prebookTimeout = (int) config('tbohotel.timeout_prebook', 23);
        $this->bookTimeout = (int) config('tbohotel.timeout_book', 120);
        $this->connectTimeout = (int) config('tbohotel.connect_timeout', 15);

        $this->client = new Client([
            'timeout' => $this->searchTimeout,
            'connect_timeout' => $this->connectTimeout,
        ]);
    }

    public function countryList(): array
    {
        return $this->request('GET', 'CountryList');
    }

    public function cityList(string $countryCode): array
    {
        return $this->request('POST', 'CityList', [
            'CountryCode' => strtoupper($countryCode),
        ]);
    }

    public function tboHotelCodeList(string $cityCode, bool $detailed = true): array
    {
        return $this->request('POST', 'TBOHotelCodeList', [
            'CityCode' => $cityCode,
            'IsDetailedResponse' => $detailed,
        ]);
    }

    public function hotelDetails(string|array $hotelCodes, string $language = 'EN', bool $roomDetails = false): array
    {
        $hotelCodes = is_array($hotelCodes) ? implode(',', $hotelCodes) : $hotelCodes;

        return $this->request('POST', 'HotelDetails', [
            'Hotelcodes' => $hotelCodes,
            'Language' => strtoupper($language),
            'IsRoomDetailRequired' => $roomDetails,
        ]);
    }

    public function search(array $payload): array
    {
        return $this->request('POST', 'Search', $payload, $this->searchTimeout);
    }

    public function preBook(array $payload): array
    {
        return $this->request('POST', 'PreBook', $payload, $this->prebookTimeout);
    }

    public function book(array $payload): array
    {
        return $this->request('POST', 'Book', $payload, $this->bookTimeout);
    }

    public function bookingDetail(array $payload): array
    {
        return $this->request('POST', 'BookingDetail', $payload);
    }

    public function bookingDetailsBasedOnDate(string $fromDate, string $toDate): array
    {
        return $this->request('POST', 'BookingDetailsBasedOnDate', [
            'FromDate' => $fromDate,
            'ToDate' => $toDate,
        ]);
    }

    public function cancel(array $payload): array
    {
        return $this->request('POST', 'Cancel', $payload);
    }

    protected function request(string $method, string $endpoint, array $payload = [], ?int $timeout = null): array
    {
        $this->ensureConfigured();

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $options = [
            'auth' => [$this->username, $this->password],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout ?? $this->searchTimeout,
            'connect_timeout' => $this->connectTimeout,
        ];

        if (strtoupper($method) !== 'GET') {
            $options['json'] = $payload;
        }

        try {
            Log::info('TBO Hotel API request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'payload' => $payload,
            ]);

            $response = $this->client->request($method, $url, $options);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                Log::warning('TBO Hotel API returned non-JSON response', [
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                    'body' => mb_substr($body, 0, 1000),
                ]);

                return [
                    'Status' => [
                        'Code' => $response->getStatusCode(),
                        'Description' => 'Invalid provider response',
                    ],
                ];
            }

            return $decoded;
        } catch (RequestException $e) {
            Log::error('TBO Hotel API request failed', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? mb_substr((string) $e->getResponse()->getBody(), 0, 2000)
                    : null,
            ]);

            throw $e;
        } catch (GuzzleException $e) {
            Log::error('TBO Hotel API guzzle error', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function ensureConfigured(): void
    {
        if (!$this->baseUrl || !$this->username || !$this->password) {
            throw new RuntimeException('TBO hotel credentials are not configured.');
        }
    }
}
