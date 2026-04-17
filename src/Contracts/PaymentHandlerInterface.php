<?php

namespace HRC\NectaPay\Contracts;

interface PaymentHandlerInterface
{
    /**
     * Handle a payment received via webhook.
     *
     * @param  string  $ownerId   The account owner's ID
     * @param  float   $amount    Net payment amount (after system fee deduction)
     * @param  array   $metadata  Webhook metadata (transaction_id, account_number, etc.)
     * @return mixed   The created payment record, or null
     */
    public function handlePayment(string $ownerId, float $amount, array $metadata): mixed;
}
