<?php

namespace HRC\NectaPay\Exceptions;

use RuntimeException;

class NectaPayException extends RuntimeException
{
    public static function authenticationFailed(string $body): self
    {
        return new self("NectaPay authentication failed: {$body}");
    }

    public static function missingToken(): self
    {
        return new self('NectaPay authentication response missing token');
    }

    public static function transferFailed(string $transactionId, string $body): self
    {
        return new self("NectaPay: Failed to initiate transfer {$transactionId}: {$body}");
    }

    public static function accountCreationFailed(string $ownerId, string $body): self
    {
        return new self("NectaPay: Failed to create virtual account for owner {$ownerId}: {$body}");
    }

    public static function batchCreationFailed(string $body): self
    {
        return new self("NectaPay: Batch account creation failed: {$body}");
    }

    public static function verificationFailed(string $body): self
    {
        return new self("NectaPay: Transaction verification failed: {$body}");
    }

    public static function retrievalFailed(string $body): self
    {
        return new self("NectaPay: Account retrieval failed: {$body}");
    }
}
