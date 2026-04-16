<?php

namespace HRC\NectaPay\DTOs;

class VirtualAccountData
{
    public function __construct(
        public readonly string $ownerId,
        public readonly ?string $ownerType = null,
        public readonly string $provider = 'nectapay',
        public readonly ?string $accountName = null,
        public readonly ?string $accountNumber = null,
        public readonly ?string $bankName = null,
        public readonly ?string $paymentRef = null,
        public readonly ?string $providerId = null,
        public readonly bool $isActive = true,
        public readonly ?array $providerResponse = null,
        public readonly ?string $id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ownerId: $data['owner_id'] ?? '',
            ownerType: $data['owner_type'] ?? null,
            provider: $data['provider'] ?? 'nectapay',
            accountName: $data['account_name'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            bankName: $data['bank_name'] ?? null,
            paymentRef: $data['payment_ref'] ?? null,
            providerId: $data['provider_id'] ?? null,
            isActive: $data['is_active'] ?? true,
            providerResponse: $data['provider_response'] ?? null,
            id: $data['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->ownerId,
            'owner_type' => $this->ownerType,
            'provider' => $this->provider,
            'account_name' => $this->accountName,
            'account_number' => $this->accountNumber,
            'bank_name' => $this->bankName,
            'payment_ref' => $this->paymentRef,
            'provider_id' => $this->providerId,
            'is_active' => $this->isActive,
            'provider_response' => $this->providerResponse,
        ];
    }
}
