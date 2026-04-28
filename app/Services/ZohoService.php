<?php

namespace App\Services;
use App\Models\ZohoCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ZohoService
{
    protected $client;
    protected $redirect_uri;
    protected $url;
    protected $base_url;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 20,
            'connect_timeout' => 10,
        ]);

        $this->redirect_uri = config('app.zoho.redirect_uri');
        $this->url = config('app.zoho.url');
        $this->base_url = config('app.zoho.base_url');
    }

    /**
     * Get token using authorization code (first-time only)
     */
    public function getToken($code, $client_id, $client_secret)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        $formParams = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri,
            'code' => $code,
        ];

        try {
            $response = $this->client->post($this->url . '/oauth/v2/token', [
                'headers' => $headers,
                'form_params' => $formParams,
            ]);

            $body = json_decode($response->getBody(), true);
            Log::info('Zoho token response:', $body);

            if (isset($body['access_token'])) {
                $this->storeOrUpdateCredentials($body);
                return $body;
            } else {
                Log::error('Zoho token response missing access_token.', ['response' => $body]);
                return null;
            }
        } catch (RequestException $e) {
            Log::error('Error retrieving Zoho token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Refresh access token using stored refresh_token
     */
    public function refreshAccessToken()
    {
        $credentials = ZohoCredentials::first();

        if (!$credentials || !$credentials->refresh_token) {
            Log::error('Zoho refresh token missing from DB.');
            return null;
        }

        try {
            $response = $this->client->post($this->url . '/oauth/v2/token', [
                'form_params' => [
                    'refresh_token' => $credentials->refresh_token,
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'redirect_uri' => $this->redirect_uri,
                    'grant_type' => 'refresh_token',
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            Log::info('Zoho refreshed token response:', $body);

            if (isset($body['access_token'])) {
                $this->storeOrUpdateCredentials($body);
                return $body['access_token'];
            }

            return null;
        } catch (RequestException $e) {
            Log::error('Error refreshing Zoho token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get valid access token from DB, refresh if needed
     */
    public function getValidAccessToken()
    {
        $credentials = ZohoCredentials::first();

        if (!$credentials || !$credentials->access_token || !$credentials->expires_in) {
            return null;
        }

        $expiresAt = $credentials->updated_at->addSeconds((int) $credentials->expires_in - 60);

        if (now()->greaterThanOrEqualTo($expiresAt)) {
            return $this->refreshAccessToken();
        }

        return $credentials->access_token;
    }

    /**
     * Store or update credentials in DB
     */
    protected function storeOrUpdateCredentials(array $body)
    {
        $accessToken = $body['access_token'];
        $refreshToken = $body['refresh_token'] ?? null;
        $expiresIn = $body['expires_in'] ?? null;

        $credentials = ZohoCredentials::first();

        if ($credentials) {
            $credentials->update([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken ?? $credentials->refresh_token,
                'expires_in' => $expiresIn,
            ]);
        }
    }

    /**
     * Get organization ID
     */
    public function getOrganizationId()
    {
        $accessToken = $this->getValidAccessToken();
        if (!$accessToken) {
            Log::error('No valid Zoho access token available.');
            return null;
        }

        try {
            $response = $this->client->get($this->base_url .'/organizations', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            Log::info('Zoho organization response:', $body);

            if (isset($body['organizations'][0]['organization_id'])) {
                return $body['organizations'][0]['organization_id'];
            }

            Log::error('Organization ID not found in Zoho response.');
            return null;

        } catch (RequestException $e) {
            Log::error('Error retrieving Zoho organization: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch or create customer
     */
    public function fetchOrCreateCustomer($params)
    {
        Log::info('Fetching or creating Zoho customer with params:', $params);
        $accessToken = $this->getValidAccessToken();
        if (!$accessToken) {
            Log::error('No valid Zoho access token available.');
            return null;
        }

        $organizationId = $this->getOrganizationId();
        if (!$organizationId) {
            return null;
        }

        try {
            // Search customer by email
            $response = $this->client->get($this->base_url .'/contacts', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ],
                'query' => [
                    'organization_id' => $organizationId,
                    'email' => $params['email'],
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            Log::info('Zoho customer search response:', $body);

            if (!empty($body['contacts'])) {
                return $body['contacts'][0];
            }

            // Create new customer if not found
            $createResponse = $this->client->post($this->base_url .'/contacts', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'organization_id' => $organizationId,
                ],
                'body' => json_encode([
                    'contact_name' => $params['name'],
                    'contact_type' => 'customer',
                    'company_name' => $params['company_name'] ?? '',
                    'billing_address' => [
                        'address' => $params['address'] ?? '',
                        'country' => $params['country'] ?? '',
                        'phone' => $params['phone'] ?? '',
                    ],
                    'contact_persons' => [
                        [
                            'first_name' => $params['name'] ?? '',
                            'email' => $params['email'],
                            'phone' => $params['phone'] ?? '',
                        ],
                    ],
                ]),
            ]);

            $createdCustomer = json_decode($createResponse->getBody(), true);
            Log::info('Zoho customer created:', $createdCustomer);

            return $createdCustomer['contact'] ?? null;

        } catch (RequestException $e) {
            Log::error('Error fetching/creating Zoho customer: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch or create currency
     */
    public function fetchOrCreateCurrency($currencyCode, $currencySymbol)
    {
        Log::info('Fetching or creating Zoho currency:', ['currency_code' => $currencyCode]);
        $accessToken = $this->getValidAccessToken();
        if (!$accessToken) {
            Log::error('No valid Zoho access token available.');
            return null;
        }

        $organizationId = $this->getOrganizationId();
        if (!$organizationId) {
            Log::error('No valid Zoho organization ID available.');
            return null;
        }

        try {
            // Search for currency
            $response = $this->client->get($this->base_url .'/settings/currencies', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ],
                'query' => [
                    'organization_id' => $organizationId,
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            Log::info('Zoho currency search response:', $body);

            if (!empty($body['currencies'])) {
                foreach ($body['currencies'] as $currency) {
                    if ($currency['currency_code'] === $currencyCode) {
                        return $currency;
                    }
                }
            }

            $createResponse = $this->client->post($this->base_url .'/currencies', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'organization_id' => $organizationId,
                ],
                'body' => json_encode([
                    'currency_code' => $currencyCode,
                    'currency_symbol' => $currencySymbol,
                ]),
            ]);

            $createdCurrency = json_decode($createResponse->getBody(), true);
            Log::info('Zoho currency created:', $createdCurrency);

            return $createdCurrency['currency'] ?? null;

        } catch (RequestException $e) {
            Log::error('Error fetching/creating Zoho currency: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper to get currency symbol (basic mapping, extend as needed)
     */


    /**
     * Create invoice for travel/tours booking
     */
    public function createInvoice($params)
    {

        Log::info($this->base_url);
        $accessToken = $this->getValidAccessToken();
        if (!$accessToken) {
            Log::error('No valid Zoho access token available.');
            return null;
        }

        $organizationId = $this->getOrganizationId();
        if (!$organizationId) {
            Log::error('No valid Zoho organization ID available.');
            return null;
        }

        // Fetch or create currency
        $currencyId = $this->fetchOrCreateCurrency($params['currency_code'], $params['currency_symbol']);
        if (!$currencyId) {
            Log::error('Failed to fetch or create currency ID.');
            return null;
        }

        // Fetch or create customer
        $customer = $this->fetchOrCreateCustomer([
            'name' => $params['customer_name'],
            'email' => $params['customer_email'],
            'address' => $params['customer_address'] ?? '',
            'country' => $params['customer_country'] ?? '',
            'phone' => $params['customer_phone'] ?? '',
            'company_name' => $params['company_name'] ?? '',
        ]);
        if (!$customer) {
            Log::error('Failed to fetch or create customer.');
            return null;
        }

        try {
            $invoiceData = [
                'customer_id' => $customer['contact_id'],
                'currency_id' => $currency['currency_id'] ?? null,
                'currency_code' => $currency['currency_code'] ?? '',
                'reference_number' => $params['reference_number'] ?? 'INV-' . time(),
                'date' => $params['date'] ?? now()->toDateString(),
                'due_date' => $params['due_date'] ?? now()->addDays(7)->toDateString(),
                'line_items' => [
                    [
                        'name' => $params['item_name'] ?? 'Travel Booking',
                        'description' => $params['item_description'] ?? 'Travel or tour service',
                        'rate' => $params['amount'] ?? 0,
                        'quantity' => $params['quantity'] ?? 1,
                    ],
                ],
                'shipping_charge' => $params['shipping_charge'] ?? 0,
                'adjustment' => $params['adjustment'] ?? 0,
                'notes' => $params['notes'] ?? 'Thank you for booking with us.',
                'terms' => $params['terms'] ?? 'Please ensure travel documents are valid.',
            ];
            $response = $this->client->post($this->base_url .'/invoices', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'organization_id' => $organizationId,
                ],
                'body' => json_encode($invoiceData),
            ]);

            $invoice = json_decode($response->getBody(), true);

            return $invoice['invoice'] ?? null;

        } catch (RequestException $e) {
            Log::error('Error creating Zoho invoice: ' . $e->getMessage());
            return null;
        }
    }

   


   public function createPayment($invoice)
{
    
    $accessToken = $this->getValidAccessToken();
    if (!$accessToken) {
        Log::error('No valid Zoho access token available.');
        return response()->json(['message' => 'No valid access token.'], 500);
    }

    $organizationId = $this->getOrganizationId();
    if (!$organizationId) {
        Log::error('No valid Zoho organization ID available.');
        return response()->json(['message' => 'No valid organization ID.'], 500);
    }

    try {
        // Step 1: Create payment
        $invoiceData = [
            "customer_id"   => $invoice['customer_id'],
            "date"          => $invoice['date'],
            "payment_mode"  => 'Card', // Can also be BankTransfer, UPI, etc.
            "amount"        => $invoice['total'],
            "invoices"      => [
                [
                    'invoice_id'     => $invoice['invoice_id'],
                    'amount_applied' => $invoice['total'],
                ],
            ],
        ];

        $response = $this->client->post($this->base_url .'/customerpayments', [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'query' => [
                'organization_id' => $organizationId,
            ],
            'body' => json_encode($invoiceData),
        ]);

        $payment = json_decode($response->getBody(), true);

        // Step 2: Update invoice status to "sent"
        $statusUrl = $this->base_url ."/invoices/{$invoice['invoice_id']}/status/sent";
        $this->client->post($statusUrl, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'query' => [
                'organization_id' => $organizationId,
            ],
        ]);

        // Step 3: (Optional) You can also approve it if needed
        // $approveUrl = $this->base_url .'/invoices/{$invoice['invoice_id']}/approve";
        // $this->client->post($approveUrl, [
        //     'headers' => [
        //         'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
        //         'Content-Type'  => 'application/json',
        //     ],
        //     'query' => [
        //         'organization_id' => $organizationId,
        //     ],
        // ]);
        // Log::info("Invoice {$invoice['invoice_id']} approved.");

        return $payment['payment'] ?? null;

    } catch (RequestException $e) {
        Log::error('Error creating Zoho payment: ' . $e->getMessage());
        return null;
    }
}


    //  public function approveInvoice($invoiceId)
    // {
    //     $accessToken = $this->getValidAccessToken();
    //     $organizationId = $this->getOrganizationId();

    //     $url = $this->base_url .'/invoices/{$invoiceId}/approve";

    //     $response = $this->client->post($url, [
    //         'headers' => [
    //             'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
    //             'Content-Type' => 'application/json'
    //         ],
    //         'query' => [
    //             'organization_id' => $organizationId,
    //         ],
    //     ]);

    //     $body = json_decode($response->getBody(), true);
    //     Log::info("Invoice {$invoiceId} approval response:", $body);

    //     return $body;
    // }


}