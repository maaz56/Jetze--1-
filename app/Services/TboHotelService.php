<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
            'IsDetailedResponse' => $detailed ? 'true' : 'false',
        ]);
    }

    public function hotelDetails(
        string|array $hotelCodes,
        string $language = 'EN',
        bool $roomDetails = false,
        bool $logRawResponse = true,
    ): array
    {
        $hotelCodes = is_array($hotelCodes) ? implode(',', $hotelCodes) : $hotelCodes;

        return $this->request('POST', 'HotelDetails', [
            'Hotelcodes' => $hotelCodes,
            'Language' => strtoupper($language),
            'IsRoomDetailRequired' => $roomDetails,
        ], null, $logRawResponse);
    }

    public function search(array $payload): array
    {
        return $this->request('POST', 'Search', $payload, $this->searchTimeout);
    }

    /**
     * Run at most three TBO Search requests concurrently.
     * Each raw request and response is logged with its batch number so a
     * city-wide progressive search can be audited independently.
     */
    public function searchConcurrent(array $payloads): array
    {
        $payloads = array_values($payloads);

        if (count($payloads) > 3) {
            throw new RuntimeException('A maximum of three hotel search batches may run concurrently.');
        }

        if ($payloads === []) {
            return [];
        }

        $this->ensureConfigured();
        $url = $this->baseUrl.'/Search';
        $promises = [];

        foreach ($payloads as $index => $payload) {
            $batchNumber = $index + 1;
            Log::info("Search Batch {$batchNumber} Request: ".json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            $promises[$index] = $this->client->requestAsync('POST', $url, [
                'auth' => [$this->username, $this->password],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $this->searchTimeout,
                'connect_timeout' => $this->connectTimeout,
            ]);
        }

        $settled = Utils::settle($promises)->wait();
        $results = [];

        foreach ($payloads as $index => $payload) {
            $batchNumber = $index + 1;
            $result = $settled[$index] ?? null;

            if (($result['state'] ?? null) === 'fulfilled') {
                $body = (string) $result['value']->getBody();
                Log::info("Search Batch {$batchNumber} Response: {$body}");
                $decoded = json_decode($body, true);

                $results[$index] = [
                    'batch_number' => $batchNumber,
                    'response' => is_array($decoded) ? $decoded : [
                        'Status' => [
                            'Code' => $result['value']->getStatusCode(),
                            'Description' => 'Invalid provider response',
                        ],
                    ],
                    'error' => null,
                ];

                continue;
            }

            $reason = $result['reason'] ?? null;
            Log::warning("Search Batch {$batchNumber} failed; retrying once.", [
                'message' => $reason instanceof Throwable ? $reason->getMessage() : 'Unknown provider error',
            ]);

            try {
                $response = $this->request('POST', 'Search', $payload, $this->searchTimeout);
                $results[$index] = [
                    'batch_number' => $batchNumber,
                    'response' => $response,
                    'error' => null,
                ];
            } catch (Throwable $e) {
                Log::error("Search Batch {$batchNumber} failed after retry.", [
                    'message' => $e->getMessage(),
                ]);

                $results[$index] = [
                    'batch_number' => $batchNumber,
                    'response' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        ksort($results);

        return array_values($results);
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

    protected function request(
        string $method,
        string $endpoint,
        array $payload = [],
        ?int $timeout = null,
        bool $logRawResponse = true,
    ): array
    {
        Log::info("{$endpoint} Request: ".json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->ensureConfigured();

        $requestId = (string) Str::uuid();
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');
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

        $startedAt = microtime(true);

        try {
            $response = $this->client->request($method, $url, $options);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            if ($logRawResponse) {
                Log::info("{$endpoint} Response: {$body}");
            } else {
                Log::info("{$endpoint} Image Response Summary", [
                    'status' => data_get($decoded, 'Status'),
                    'hotel_count' => collect(data_get($decoded, 'HotelDetails', []))->count(),
                    'image_count_by_hotel' => collect(data_get($decoded, 'HotelDetails', []))
                        ->filter(fn ($hotel) => is_array($hotel))
                        ->mapWithKeys(fn (array $hotel) => [
                            (string) ($hotel['HotelCode'] ?? 'unknown') => count($hotel['Images'] ?? []) + (! empty($hotel['Image']) ? 1 : 0),
                        ])
                        ->all(),
                    'response_bytes' => strlen($body),
                ]);
            }

            if (! is_array($decoded)) {
                Log::warning('TBO Hotel API returned non-JSON response', [
                    'request_id' => $requestId,
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                    'duration_ms' => $this->requestDurationMs($startedAt),
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
                'request_id' => $requestId,
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'duration_ms' => $this->requestDurationMs($startedAt),
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? mb_substr((string) $e->getResponse()->getBody(), 0, 2000)
                    : null,
            ]);

            throw $e;
        } catch (GuzzleException $e) {
            Log::error('TBO Hotel API guzzle error', [
                'request_id' => $requestId,
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'duration_ms' => $this->requestDurationMs($startedAt),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** Return a rounded duration suitable for correlating request and response log entries. */
    protected function requestDurationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    protected function ensureConfigured(): void
    {
        if (! $this->baseUrl || ! $this->username || ! $this->password) {
            throw new RuntimeException('TBO hotel credentials are not configured.');
        }
    }
}
