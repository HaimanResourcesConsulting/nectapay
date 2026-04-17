<?php

namespace HRC\NectaPay\Laravel;

use HRC\NectaPay\NectaPayClient;
use HRC\NectaPay\Laravel\Models\VirtualAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NectaPayService
{
    public function __construct(
        private NectaPayClient $client,
    ) {}

    /**
     * Create a static virtual account for a single owner.
     */
    public function createStaticAccount(Model $owner): VirtualAccount
    {
        if (method_exists($owner, 'loadMissing')) {
            $owner->loadMissing('user');
        }

        $config = $this->client->getConfig();
        $prefix = $config->accountPrefix;
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

        $data = $this->client->createStaticAccount($accountName, $paymentRef);

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
                'provider_response' => $data,
            ]
        );
    }

    /**
     * Create virtual accounts in batch.
     */
    public function createBatchAccounts(Collection $owners): array
    {
        $config = $this->client->getConfig();
        $prefix = $config->accountPrefix;

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

        $fullResponse = $this->client->createBatchAccounts($payload);
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
     * Initiate a dynamic virtual account transfer (single transaction).
     *
     * @param  float   $amount         Exact amount expected
     * @param  string  $transactionId  Unique transaction reference
     * @param  string  $description    Narration / description
     * @return array   Dynamic account details (account_number, bank_name, expires_in_minutes, etc.)
     */
    public function initiateTransfer(float $amount, string $transactionId, string $description = ''): array
    {
        return $this->client->initiateTransfer($amount, $transactionId, $description);
    }

    public function verifyTransaction(string $transactionId): array
    {
        return $this->client->verifyTransaction($transactionId);
    }

    public function retrieveAccount(string $paymentRef): array
    {
        return $this->client->retrieveAccount($paymentRef);
    }

    public function validateWebhookHash(array $payload): bool
    {
        return $this->client->validateWebhookHash($payload);
    }

    public function clearAuthToken(): void
    {
        $this->client->clearAuthToken();
    }

    public function getAuthToken(): string
    {
        return $this->client->getAuthToken();
    }

    public function getOwnerIdentifier(Model $owner): string
    {
        $attribute = $this->client->getConfig()->ownerIdAttribute;

        if ($attribute && isset($owner->{$attribute})) {
            return (string) $owner->{$attribute};
        }

        return (string) $owner->getKey();
    }

    public function getClient(): NectaPayClient
    {
        return $this->client;
    }
}
