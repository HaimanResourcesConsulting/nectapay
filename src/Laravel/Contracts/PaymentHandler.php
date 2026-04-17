<?php

namespace HRC\NectaPay\Laravel\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PaymentHandler
{
    /**
     * Record and confirm a payment from a NectaPay webhook.
     *
     * @param  Model   $owner       The account owner model instance (student, customer, user, etc.).
     * @param  float   $amount      Net amount after system fee deduction.
     * @param  array   $metadata    Webhook metadata (transaction_id, account_number, etc.).
     * @return Model|null           The created payment model, or null.
     */
    public function handleWebhookPayment(Model $owner, float $amount, array $metadata): ?Model;
}
