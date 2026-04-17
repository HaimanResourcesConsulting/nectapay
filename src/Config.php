<?php

namespace HRC\NectaPay;

class Config
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $merchantId,
        public readonly string $webhookSecret,
        public readonly float $systemFee = 200,
        public readonly string $accountPrefix = 'APP',
        public readonly ?string $ownerIdAttribute = null,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            baseUrl: rtrim($config['base_url'] ?? '', '/'),
            apiKey: $config['api_key'] ?? '',
            merchantId: $config['merchant_id'] ?? '',
            webhookSecret: $config['webhook_secret'] ?? '',
            systemFee: (float) ($config['system_fee'] ?? 200),
            accountPrefix: $config['account_prefix'] ?? 'APP',
            ownerIdAttribute: $config['owner_id_attribute'] ?? null,
        );
    }
}
