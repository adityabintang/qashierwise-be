<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CatalogEmbeddedSignupService
{
    protected string $apiVersion;

    protected ?string $appId;

    protected ?string $appSecret;

    protected ?string $configId;

    public function __construct()
    {
        $this->apiVersion = config('whatsapp.api_version', 'v22.0');
        $this->appId = config('whatsapp.catalog_embedded_signup.app_id');
        $this->appSecret = config('whatsapp.catalog_embedded_signup.app_secret');
        $this->configId = config('whatsapp.catalog_embedded_signup.config_id');
    }

    public function isEnabled(): bool
    {
        return ! empty($this->appId) && ! empty($this->appSecret) && ! empty($this->configId);
    }

    protected function getBaseUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * @return array{success: bool, access_token?: string, expires_in?: int|null, error?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Catalog Embedded Signup is not configured',
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
                    Log::info('Successfully exchanged catalog code for access token');

                    return [
                        'success' => true,
                        'access_token' => $data['access_token'],
                        'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
                    ];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Failed to exchange code for token';

            Log::error('Catalog token exchange failed', [
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Catalog token exchange exception', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to connect to Facebook API: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array{access_token: string, expires_in?: int|null, business_id?: string|null}  $credentials
     */
    public function storeCatalogCredentials(WhatsAppAccount $account, array $credentials): WhatsAppAccount
    {
        $account->catalog_access_token = $credentials['access_token'];
        $account->catalog_token_expires_at = isset($credentials['expires_in']) && $credentials['expires_in']
            ? now()->addSeconds((int) $credentials['expires_in'])
            : null;

        if (! empty($credentials['business_id'])) {
            $account->catalog_business_id = $credentials['business_id'];
        }

        $account->save();

        Log::info('Stored catalog credentials for user', [
            'user_id' => $account->user_id,
        ]);

        return $account;
    }

    /**
     * @param  array{business_id?: string|null}  $sessionInfo
     * @return array{success: bool, account?: WhatsAppAccount, error?: string}
     */
    public function processSignup(WhatsAppAccount $account, string $code, array $sessionInfo = []): array
    {
        $tokenResult = $this->exchangeCodeForToken($code);
        if (! $tokenResult['success']) {
            return [
                'success' => false,
                'error' => $tokenResult['error'],
            ];
        }

        $credentials = [
            'access_token' => $tokenResult['access_token'],
            'expires_in' => $tokenResult['expires_in'] ?? null,
            'business_id' => $sessionInfo['business_id'] ?? null,
        ];

        $updatedAccount = $this->storeCatalogCredentials($account, $credentials);

        return [
            'success' => true,
            'account' => $updatedAccount,
        ];
    }
}
