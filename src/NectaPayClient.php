<?php

namespace HRC\NectaPay;

use HRC\NectaPay\Contracts\CacheInterface;
use HRC\NectaPay\Contracts\HttpClientInterface;
use HRC\NectaPay\Exceptions\NectaPayException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NectaPayClient
{
    private LoggerInterface $logger;

    public function __construct(
        private Config $config,
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Authenticate and get bearer token (cached).
     */
    public function getAuthToken(): string
    {
        $cached = $this->cache->get('nectapay_auth_token');
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->httpClient->get(
            "{$this->config->baseUrl}/validation",
            $this->baseHeaders()
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $body = json_encode($response['body']);
            $this->logger->error('NectaPay: Authentication failed', [
                'status' => $response['status'],
                'body' => $body,
            ]);
            throw NectaPayException::authenticationFailed($body);
        }

        $body = $response['body'];
        $token = $body['data']['auth']['access_token']
            ?? $body['token']
            ?? $body['data']['token']
            ?? null;

        if (!$token) {
            $this->logger->error('NectaPay: Authentication response missing token', [
                'body' => json_encode($body),
            ]);
            throw NectaPayException::missingToken();
        }

        $this->cache->set('nectapay_auth_token', $token, 3500);

        return $token;
    }

    /**
     * Create a static virtual account.
     *
     * @param  string  $accountName  Display name for the account
     * @param  string  $paymentRef   Unique payment reference
     * @return array   API response data
     */
    public function createStaticAccount(string $accountName, string $paymentRef): array
    {
        $response = $this->authenticatedPost('/static_account', [
            'account_name' => $accountName,
            'merchant_id' => $this->config->merchantId,
            'payment_ref' => $paymentRef,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $body = json_encode($response['body']);
            $this->logger->error('NectaPay: Failed to create static account', [
                'payment_ref' => $paymentRef,
                'status' => $response['status'],
                'body' => $body,
            ]);
            throw NectaPayException::accountCreationFailed($paymentRef, $body);
        }

        return $response['body']['data'] ?? $response['body'];
    }

    /**
     * Create virtual accounts in batch.
     *
     * @param  array  $accounts  Array of ['account_name' => '...', 'payment_ref' => '...']
     * @return array  API response data
     */
    public function createBatchAccounts(array $accounts): array
    {
        $response = $this->authenticatedPost('/batch_static_account', [
            'payload' => $accounts,
            'merchant_id' => $this->config->merchantId,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $body = json_encode($response['body']);
            $this->logger->error('NectaPay: Batch account creation failed', [
                'count' => count($accounts),
                'status' => $response['status'],
                'body' => $body,
            ]);
            throw NectaPayException::batchCreationFailed($body);
        }

        return $response['body'];
    }

    /**
     * Verify a transaction by its transaction ID.
     */
    public function verifyTransaction(string $transactionId): array
    {
        $response = $this->authenticatedPost('/verify_transaction', [
            'transaction_id' => $transactionId,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $body = json_encode($response['body']);
            $this->logger->error('NectaPay: Transaction verification failed', [
                'transaction_id' => $transactionId,
                'status' => $response['status'],
                'body' => $body,
            ]);
            throw NectaPayException::verificationFailed($body);
        }

        return $response['body'];
    }

    /**
     * Retrieve account number details by payment reference.
     */
    public function retrieveAccount(string $paymentRef): array
    {
        $response = $this->authenticatedPost('/retrieve_account_number', [
            'payment_ref' => $paymentRef,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw NectaPayException::retrievalFailed(json_encode($response['body']));
        }

        return $response['body'];
    }

    /**
     * Validate a webhook hash against the configured secret.
     */
    public function validateWebhookHash(array $payload): bool
    {
        $receivedHash = $payload['hash_key'] ?? null;

        if (!$receivedHash || !$this->config->webhookSecret) {
            return false;
        }

        if (hash_equals($this->config->webhookSecret, $receivedHash)) {
            return true;
        }

        $computedHash = hash('sha512',
            $this->config->webhookSecret
            . ($payload['TransactionId'] ?? '')
            . ($payload['AmountPaid'] ?? '')
        );

        return hash_equals($computedHash, $receivedHash);
    }

    /**
     * Clear the cached auth token.
     */
    public function clearAuthToken(): void
    {
        $this->cache->forget('nectapay_auth_token');
    }

    /**
     * Get the config object.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Make an authenticated POST request.
     */
    private function authenticatedPost(string $path, array $data): array
    {
        $token = $this->getAuthToken();

        return $this->httpClient->post(
            "{$this->config->baseUrl}{$path}",
            $data,
            array_merge($this->baseHeaders(), [
                'Authorization' => "Bearer {$token}",
            ])
        );
    }

    private function baseHeaders(): array
    {
        return [
            'API-Key' => $this->config->apiKey,
            'Merchant-Id' => $this->config->merchantId,
        ];
    }
}
