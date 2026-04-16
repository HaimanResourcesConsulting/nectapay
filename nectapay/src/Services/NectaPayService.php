<?php

namespace HRC\NectaPay\Services;

use HRC\NectaPay\Exceptions\NectaPayException;
use HRC\NectaPay\Models\VirtualAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NectaPayService
{
    private string $baseUrl;
    private string $apiKey;
    private string $merchantId;
    private string $webhookSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('nectapay.base_url'), '/');
        $this->apiKey = config('nectapay.api_key');
        $this->merchantId = config('nectapay.merchant_id');
        $this->webhookSecret = config('nectapay.webhook_secret');
    }

    /**
     * Get an authenticated HTTP client with bearer token.
     */
    protected function client(): PendingRequest
    {
        $token = $this->getAuthToken();

        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'API-Key' => $this->apiKey,
                'Merchant-Id' => $this->merchantId,
            ])
            ->withToken($token)
            ->timeout(30)
            ->retry(2, 500);
    }

    /**
     * Authenticate and cache the bearer token.
     */
    public function getAuthToken(): string
    {
        return Cache::remember('nectapay_auth_token', 3500, function () {
            $response = Http::withHeaders([
                'API-Key' => $this->apiKey,
                'Merchant-Id' => $this->merchantId,
            ])->get("{$this->baseUrl}/validation");

            if (!$response->successful()) {
                Log::error('NectaPay: Authentication failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw NectaPayException::authenticationFailed($response->body());
            }

            $token = $response->json('data.auth.access_token')
                ?? $response->json('token')
                ?? $response->json('data.token');

            if (!$token) {
                Log::error('NectaPay: Authentication response missing token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw NectaPayException::missingToken();
            }

            return $token;
        });
    }

    /**
     * Create a static virtual account for a single owner (student, customer, user, etc.).
     *
     * @param  Model  $owner  Must have 'id' and 'name' attributes.
     */
    public function createStaticAccount(Model $owner): VirtualAccount
    {
        if (method_exists($owner, 'loadMissing')) {
            $owner->loadMissing('user');
        }

        $prefix = config('nectapay.account_prefix', 'APP');
        $ownerId = $this->getOwnerIdentifier($owner);
        $paymentRef = strtolower($prefix) . "_owner_{$ownerId}";
        $accountName = "{$prefix} - " . ($owner->name ?? "Account {$ownerId}");

        // Check if already exists
        $existing = VirtualAccount::where('owner_id', $owner->getKey())
            ->where('provider', 'nectapay')
            ->first();

        if ($existing && $existing->account_number) {
            return $existing;
        }

        $response = $this->client()->post('/static_account', [
            'account_name' => $accountName,
            'merchant_id' => $this->merchantId,
            'payment_ref' => $paymentRef,
        ]);

        if (!$response->successful()) {
            Log::error('NectaPay: Failed to create static account', [
                'owner_id' => $owner->getKey(),
                'payment_ref' => $paymentRef,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw NectaPayException::accountCreationFailed($ownerId, $response->body());
        }

        $fullResponse = $response->json();
        $data = $fullResponse['data'] ?? $fullResponse;

        return VirtualAccount::updateOrCreate(
            [
                'owner_id' => $owner->getKey(),
                'provider' => 'nectapay',
            ],
            [
                'owner_type' => get_class($owner),
                'account_name' => $data['account_name'] ?? $accountName,
                'account_number' => $data['account_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'payment_ref' => $paymentRef,
                'provider_id' => $data['id'] ?? null,
                'is_active' => true,
                'provider_response' => $fullResponse,
            ]
        );
    }

    /**
     * Create virtual accounts in batch.
     *
     * @param  Collection  $owners  Collection of owner models.
     */
    public function createBatchAccounts(Collection $owners): array
    {
        $prefix = config('nectapay.account_prefix', 'APP');
        $payload = $owners->map(function (Model $owner) use ($prefix) {
            if (method_exists($owner, 'loadMissing')) {
                $owner->loadMissing('user');
            }
            $ownerId = $this->getOwnerIdentifier($owner);
            return [
                'account_name' => ($owner->name ?? "Account {$ownerId}") . " - {$prefix}",
                'payment_ref' => "owner_{$ownerId}",
            ];
        })->values()->toArray();

        $response = $this->client()->post('/batch_static_account', [
            'payload' => $payload,
            'merchant_id' => $this->merchantId,
        ]);

        if (!$response->successful()) {
            Log::error('NectaPay: Batch account creation failed', [
                'count' => count($payload),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw NectaPayException::batchCreationFailed($response->body());
        }

        $fullResponse = $response->json();
        $accounts = [];

        $responseItems = $fullResponse['data'] ?? $fullResponse['accounts'] ?? $fullResponse;
        if (!is_array($responseItems)) {
            $responseItems = [];
        }

        foreach ($owners as $index => $owner) {
            $item = $responseItems[$index] ?? null;
            if (!$item) {
                continue;
            }

            $ownerId = $this->getOwnerIdentifier($owner);

            $accounts[] = VirtualAccount::updateOrCreate(
                [
                    'owner_id' => $owner->getKey(),
                    'provider' => 'nectapay',
                ],
                [
                    'owner_type' => get_class($owner),
                    'account_name' => $item['account_name'] ?? $owner->name,
                    'account_number' => $item['account_number'] ?? null,
                    'bank_name' => $item['bank_name'] ?? null,
                    'payment_ref' => "owner_{$ownerId}",
                    'provider_id' => $item['id'] ?? null,
                    'is_active' => true,
                    'provider_response' => $item,
                ]
            );
        }

        return $accounts;
    }

    /**
     * Verify a transaction by its transaction ID.
     */
    public function verifyTransaction(string $transactionId): array
    {
        $response = $this->client()->post('/verify_transaction', [
            'transaction_id' => $transactionId,
        ]);

        if (!$response->successful()) {
            Log::error('NectaPay: Transaction verification failed', [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw NectaPayException::verificationFailed($response->body());
        }

        return $response->json();
    }

    /**
     * Retrieve account number details by payment reference.
     */
    public function retrieveAccount(string $paymentRef): array
    {
        $response = $this->client()->post('/retrieve_account_number', [
            'payment_ref' => $paymentRef,
        ]);

        if (!$response->successful()) {
            throw NectaPayException::retrievalFailed($response->body());
        }

        return $response->json();
    }

    /**
     * Validate a webhook hash against the expected value.
     */
    public function validateWebhookHash(array $payload): bool
    {
        $receivedHash = $payload['hash_key'] ?? null;

        if (!$receivedHash || !$this->webhookSecret) {
            return false;
        }

        // NectaPay sends the webhook_secret as the hash_key for verification
        if (hash_equals($this->webhookSecret, $receivedHash)) {
            return true;
        }

        // Fallback: check sha512(secret + TransactionId + AmountPaid)
        $computedHash = hash('sha512', $this->webhookSecret . ($payload['TransactionId'] ?? '') . ($payload['AmountPaid'] ?? ''));

        return hash_equals($computedHash, $receivedHash);
    }

    /**
     * Clear the cached auth token.
     */
    public function clearAuthToken(): void
    {
        Cache::forget('nectapay_auth_token');
    }

    /**
     * Get the human-readable identifier for an owner model.
     * Uses the configured owner_id_attribute, or falls back to the primary key.
     */
    public function getOwnerIdentifier(Model $owner): string
    {
        $attribute = config('nectapay.owner_id_attribute');

        if ($attribute && isset($owner->{$attribute})) {
            return (string) $owner->{$attribute};
        }

        return (string) $owner->getKey();
    }
}
