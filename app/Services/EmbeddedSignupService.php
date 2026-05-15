<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service class for handling WhatsApp Embedded Signup v4 flow.
 *
 * This service manages the OAuth token exchange, WABA details retrieval,
 * and credential storage for users connecting their WhatsApp Business accounts.
 */
class EmbeddedSignupService
{
    protected string $apiVersion;

    protected ?string $appId;

    protected ?string $appSecret;

    protected ?string $configId;

    public function __construct()
    {
        $this->apiVersion = config('whatsapp.api_version', 'v22.0');
        $this->appId = config('whatsapp.embedded_signup.app_id');
        $this->appSecret = config('whatsapp.embedded_signup.app_secret');
        $this->configId = config('whatsapp.embedded_signup.config_id');
    }

    /**
     * Check if Embedded Signup feature is enabled.
     */
    public function isEnabled(): bool
    {
        return ! empty($this->appId) && ! empty($this->appSecret) && ! empty($this->configId);
    }

    /**
     * Get the base URL for Facebook Graph API.
     */
    protected function getBaseUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param  string  $code  Authorization code from Facebook SDK
     * @return array{success: bool, access_token?: string, error?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Embedded Signup is not configured',
            ];
        }

        try {
            $response = Http::get($this->getBaseUrl().'/oauth/access_token', [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'code' => $code,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['access_token'])) {
                    Log::info('Successfully exchanged code for access token');

                    return [
                        'success' => true,
                        'access_token' => $data['access_token'],
                    ];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Failed to exchange code for token';

            Log::error('Token exchange failed', [
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Token exchange exception', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to connect to Facebook API: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get WABA (WhatsApp Business Account) details from access token.
     *
     * @param  string  $accessToken  Access token from OAuth flow
     * @return array{success: bool, waba_id?: string, phone_numbers?: array, error?: string}
     */
    public function getWABADetails(string $accessToken): array
    {
        try {
            // First, debug the token to get the granular scopes and WABA ID
            $debugResponse = Http::get($this->getBaseUrl().'/debug_token', [
                'input_token' => $accessToken,
                'access_token' => $this->appId.'|'.$this->appSecret,
            ]);

            if (! $debugResponse->successful()) {
                $errorData = $debugResponse->json();

                return [
                    'success' => false,
                    'error' => $errorData['error']['message'] ?? 'Failed to debug token',
                ];
            }

            $debugData = $debugResponse->json();
            $granularScopes = $debugData['data']['granular_scopes'] ?? [];

            // Find the WABA ID from granular scopes
            $wabaId = null;
            foreach ($granularScopes as $scope) {
                if ($scope['scope'] === 'whatsapp_business_management' && ! empty($scope['target_ids'])) {
                    $wabaId = $scope['target_ids'][0];
                    break;
                }
            }

            if (! $wabaId) {
                return [
                    'success' => false,
                    'error' => 'No WhatsApp Business Account found in token scopes',
                ];
            }

            Log::info('Retrieved WABA ID from token', ['waba_id' => $wabaId]);

            return [
                'success' => true,
                'waba_id' => $wabaId,
            ];
        } catch (\Exception $e) {
            Log::error('WABA details retrieval exception', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve WABA details: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get phone number details for a WABA.
     *
     * @param  string  $accessToken  Access token
     * @param  string  $wabaId  WABA ID
     * @return array{success: bool, phone_numbers?: array, error?: string}
     */
    public function getPhoneNumberDetails(string $accessToken, string $wabaId): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get($this->getBaseUrl()."/{$wabaId}/phone_numbers", [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $phoneNumbers = $data['data'] ?? [];

                if (empty($phoneNumbers)) {
                    return [
                        'success' => false,
                        'error' => 'No phone numbers found for this WABA',
                    ];
                }

                Log::info('Retrieved phone numbers for WABA', [
                    'waba_id' => $wabaId,
                    'count' => count($phoneNumbers),
                ]);

                return [
                    'success' => true,
                    'phone_numbers' => $phoneNumbers,
                ];
            }

            $errorData = $response->json();

            return [
                'success' => false,
                'error' => $errorData['error']['message'] ?? 'Failed to retrieve phone numbers',
            ];
        } catch (\Exception $e) {
            Log::error('Phone number details retrieval exception', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve phone numbers: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Store or update WhatsApp account credentials for a user.
     *
     * @param  int  $userId  User ID
     * @param  array  $credentials  Credentials array containing phone_number_id, waba_id, access_token, etc.
     */
    public function storeCredentials(int $userId, array $credentials): WhatsAppAccount
    {
        // Prepare the data for storage
        // Note: API uses display_name/verified_name, but DB uses display_phone_number/name
        $data = [
            'user_id' => $userId,
            'phone_number_id' => $credentials['phone_number_id'],
            'waba_id' => $credentials['waba_id'],
            'business_account_id' => $credentials['waba_id'], // For backward compatibility
            'access_token' => $credentials['access_token'],
            'display_phone_number' => $credentials['display_name'] ?? null,
            'name' => $credentials['verified_name'] ?? null,
            'quality_rating' => $credentials['quality_rating'] ?? null,
            'is_active' => true,
            'coexistence_enabled' => $credentials['coexistence_enabled'] ?? true,
            'connection_method' => 'embedded_signup',
        ];

        if (! empty($credentials['catalog_business_id'])) {
            $data['catalog_business_id'] = $credentials['catalog_business_id'];
        }

        // Add token expiration if provided
        if (isset($credentials['token_expires_at'])) {
            $data['token_expires_at'] = $credentials['token_expires_at'];
        }

        // Use updateOrCreate to prevent duplicates (upsert)
        // Bypass RLS for the upsert operation
        $account = WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->updateOrCreate(
                ['user_id' => $userId],
                $data
            );

        Log::info('Stored WhatsApp credentials for user', [
            'user_id' => $userId,
            'phone_number_id' => $credentials['phone_number_id'],
            'waba_id' => $credentials['waba_id'],
        ]);

        return $account;
    }

    /**
     * Validate an access token by making a test API call.
     *
     * @param  string  $accessToken  Access token to validate
     * @return bool True if token is valid, false otherwise
     */
    public function validateToken(string $accessToken): bool
    {
        try {
            $response = Http::get($this->getBaseUrl().'/debug_token', [
                'input_token' => $accessToken,
                'access_token' => $this->appId.'|'.$this->appSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $isValid = $data['data']['is_valid'] ?? false;

                // Also check if token is not expired
                if ($isValid && isset($data['data']['expires_at'])) {
                    $expiresAt = $data['data']['expires_at'];
                    // expires_at of 0 means the token never expires
                    if ($expiresAt !== 0 && $expiresAt < time()) {
                        return false;
                    }
                }

                return $isValid;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Token validation exception', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Subscribe WABA to receive webhooks from this app.
     * This is required for receiving template status updates, message status updates, etc.
     *
     * @param  string  $accessToken  Access token with whatsapp_business_management permission
     * @param  string  $wabaId  WABA ID to subscribe
     * @return array{success: bool, error?: string}
     */
    public function subscribeToWebhooks(string $accessToken, string $wabaId): array
    {
        try {
            Log::info('Subscribing WABA to webhooks', ['waba_id' => $wabaId]);

            $response = Http::withToken($accessToken)
                ->post($this->getBaseUrl()."/{$wabaId}/subscribed_apps");

            if ($response->successful()) {
                $data = $response->json();

                if ($data['success'] ?? false) {
                    Log::info('Successfully subscribed WABA to webhooks', [
                        'waba_id' => $wabaId,
                    ]);

                    return ['success' => true];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Failed to subscribe to webhooks';

            Log::warning('Failed to subscribe WABA to webhooks', [
                'waba_id' => $wabaId,
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Webhook subscription exception', [
                'waba_id' => $wabaId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to subscribe to webhooks: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check if WABA is subscribed to webhooks.
     *
     * @param  string  $accessToken  Access token
     * @param  string  $wabaId  WABA ID
     * @return array{success: bool, subscribed?: bool, apps?: array, error?: string}
     */
    public function checkWebhookSubscription(string $accessToken, string $wabaId): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get($this->getBaseUrl()."/{$wabaId}/subscribed_apps");

            if ($response->successful()) {
                $data = $response->json();
                $apps = $data['data'] ?? [];

                // Check if our app is in the subscribed list
                // The response structure can be: {id: "..."} or {whatsapp_business_api_data: {id: "..."}}
                $isSubscribed = false;
                foreach ($apps as $app) {
                    $appId = $app['id'] ?? $app['whatsapp_business_api_data']['id'] ?? null;
                    if ($appId === $this->appId) {
                        $isSubscribed = true;
                        break;
                    }
                }

                return [
                    'success' => true,
                    'subscribed' => $isSubscribed,
                    'apps' => $apps,
                ];
            }

            $errorData = $response->json();

            return [
                'success' => false,
                'error' => $errorData['error']['message'] ?? 'Failed to check webhook subscription',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to check webhook subscription: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Process the complete embedded signup flow.
     *
     * This method orchestrates the entire flow:
     * 1. Exchange code for token
     * 2. Get WABA details (from session info or API fallback)
     * 3. Get phone number details
     * 4. Store credentials
     *
     * @param  int  $userId  User ID
     * @param  string  $code  Authorization code from Facebook SDK
     * @param  array  $sessionInfo  Optional session info from embedded signup containing waba_id, phone_number_id, business_id
     * @return array{success: bool, account?: WhatsAppAccount, error?: string}
     */
    public function processSignup(int $userId, string $code, array $sessionInfo = []): array
    {
        // Step 1: Exchange code for token
        $tokenResult = $this->exchangeCodeForToken($code);
        if (! $tokenResult['success']) {
            return [
                'success' => false,
                'error' => $tokenResult['error'],
            ];
        }

        $accessToken = $tokenResult['access_token'];

        // Step 2: Get WABA ID - prefer session info from embedded signup, fallback to API
        $wabaId = null;
        $phoneNumberId = null;

        if (! empty($sessionInfo['waba_id'])) {
            // Use waba_id directly from embedded signup session info
            $wabaId = $sessionInfo['waba_id'];
            $phoneNumberId = $sessionInfo['phone_number_id'] ?? null;

            Log::info('Using WABA ID from embedded signup session info', [
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'business_id' => $sessionInfo['business_id'] ?? null,
            ]);
        } else {
            // Fallback: Get WABA details from debug_token API
            $wabaResult = $this->getWABADetails($accessToken);
            if (! $wabaResult['success']) {
                return [
                    'success' => false,
                    'error' => $wabaResult['error'],
                ];
            }
            $wabaId = $wabaResult['waba_id'];

            Log::info('Using WABA ID from debug_token API (fallback)', ['waba_id' => $wabaId]);
        }

        // Step 3: Get phone number details
        $phoneResult = $this->getPhoneNumberDetails($accessToken, $wabaId);
        if (! $phoneResult['success']) {
            return [
                'success' => false,
                'error' => $phoneResult['error'],
            ];
        }

        // Use phone_number_id from session info if available, otherwise use first from API
        $phoneNumber = null;
        if ($phoneNumberId) {
            // Find matching phone number from API response
            foreach ($phoneResult['phone_numbers'] as $pn) {
                if ($pn['id'] === $phoneNumberId) {
                    $phoneNumber = $pn;
                    break;
                }
            }
        }

        // Fallback to first phone number if not found
        if (! $phoneNumber) {
            $phoneNumber = $phoneResult['phone_numbers'][0];
        }

        // Step 4: Store credentials
        $credentials = [
            'phone_number_id' => $phoneNumber['id'],
            'waba_id' => $wabaId,
            'access_token' => $accessToken,
            'display_name' => $phoneNumber['display_phone_number'] ?? null,
            'verified_name' => $phoneNumber['verified_name'] ?? null,
            'quality_rating' => $phoneNumber['quality_rating'] ?? null,
            'coexistence_enabled' => true,
        ];

        // Persist business_id from session info so catalog features work without separate OAuth
        if (! empty($sessionInfo['business_id'])) {
            $credentials['catalog_business_id'] = $sessionInfo['business_id'];
        }

        $account = $this->storeCredentials($userId, $credentials);

        // Step 5: Subscribe WABA to webhooks (for receiving template status updates, etc.)
        $webhookResult = $this->subscribeToWebhooks($accessToken, $wabaId);
        if (! $webhookResult['success']) {
            // Log warning but don't fail - webhook subscription is important but not critical
            Log::warning('Failed to auto-subscribe WABA to webhooks during signup', [
                'waba_id' => $wabaId,
                'user_id' => $userId,
                'error' => $webhookResult['error'] ?? 'Unknown error',
            ]);
        } else {
            Log::info('Successfully subscribed WABA to webhooks during signup', [
                'waba_id' => $wabaId,
                'user_id' => $userId,
            ]);
        }

        return [
            'success' => true,
            'account' => $account,
        ];
    }
}
