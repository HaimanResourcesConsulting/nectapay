<?php

namespace HRC\NectaPay\DTOs;

class WebhookResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly int $httpStatus = 200,
        public readonly ?string $paymentId = null,
    ) {}

    public static function ok(string $message = 'Payment processed', ?string $paymentId = null): self
    {
        return new self('ok', $message, 200, $paymentId);
    }

    public static function error(string $message, int $httpStatus = 400): self
    {
        return new self('error', $message, $httpStatus);
    }

    public static function duplicate(string $message = 'Already processed'): self
    {
        return new self('ok', $message, 200);
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'ok';
    }
}
