<?php

namespace HRC\NectaPay\Laravel\Http\Controllers;

use HRC\NectaPay\Laravel\Contracts\PaymentHandler;
use HRC\NectaPay\Laravel\Exceptions\WebhookEarlyExitException;
use HRC\NectaPay\Laravel\Models\VirtualAccount;
use HRC\NectaPay\Laravel\Models\WebhookLog;
use HRC\NectaPay\Laravel\NectaPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class NectaPayWebhookController extends Controller
{
    public function __construct(
        private NectaPayService $nectaPay,
    ) {}

    /**
     * Handle incoming NectaPay webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('NectaPay webhook received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $request->all(),
        ]);

        $rawContent = $request->getContent();
        $fullPayload = $request->all();

        // NectaPay wraps transaction fields under 'data' key — extract them
        $payload = $fullPayload['data'] ?? $fullPayload;
        $payload['hash_key'] = $fullPayload['hash_key'] ?? $payload['hash_key'] ?? null;

        // Log the raw webhook immediately for audit
        $webhookLog = WebhookLog::create([
            'transaction_id' => $payload['TransactionId'] ?? 'unknown',
            'account_number' => $payload['AccountNumber'] ?? null,
            'payment_ref' => $payload['CustomerId'] ?? $payload['payment_ref'] ?? null,
            'amount_paid' => $payload['AmountPaid'] ?? null,
            'status' => 'received',
            'payload' => $fullPayload,
            'raw_payload' => json_decode($rawContent, true) ?? ['raw' => $rawContent],
            'amounts' => [
                'amount_expected' => $payload['AmountExpected'] ?? null,
                'settlement_amount' => $payload['SettlementAmountDue'] ?? null,
                'charges' => $payload['Charges'] ?? null,
            ],
            'hash_details' => [
                'received' => $payload['hash_key'] ?? null,
                'computed' => null,
                'valid' => false,
            ],
            'metadata' => [
                'ip_address' => $request->ip(),
                'event' => $fullPayload['webhook_event'] ?? $payload['event'] ?? 'payment',
                'account_type' => $payload['AccountType'] ?? null,
            ],
        ]);

        try {
            $this->validateHash($webhookLog, $payload, $request);
            $this->checkIdempotency($webhookLog, $payload);

            $virtualAccount = $this->resolveVirtualAccount($webhookLog, $payload);
            $owner = $virtualAccount->owner;
            $amountPaid = (float) ($payload['AmountPaid'] ?? 0);

            if ($amountPaid <= 0) {
                throw new WebhookEarlyExitException(
                    $this->failWebhook($webhookLog, 'Invalid payment amount', 400)
                );
            }

            Log::info('NectaPay webhook: Validated and resolved virtual account', [
                'webhook_log_id' => $webhookLog->id,
                'owner_id' => $owner->getKey(),
                'amount_paid' => $amountPaid,
            ]);

            return $this->processPayment($webhookLog, $owner, $amountPaid, $payload);

        } catch (WebhookEarlyExitException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('NectaPay webhook: Processing failed', [
                'webhook_log_id' => $webhookLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Processing failed'], 500);
        }
    }

    private function validateHash(WebhookLog $webhookLog, array $payload, Request $request): void
    {
        $isValid = $this->nectaPay->validateWebhookHash($payload);
        $computedHash = hash('sha512', config('nectapay.webhook_secret') . ($payload['TransactionId'] ?? '') . ($payload['AmountPaid'] ?? ''));

        $webhookLog->update([
            'hash_details' => [
                'received' => $payload['hash_key'] ?? null,
                'computed' => $computedHash,
                'valid' => $isValid,
            ],
        ]);

        if (!$isValid) {
            Log::warning('NectaPay webhook: Invalid hash', [
                'webhook_log_id' => $webhookLog->id,
                'ip' => $request->ip(),
            ]);

            throw new WebhookEarlyExitException(
                $this->failWebhook($webhookLog, 'Invalid webhook hash', 400)
            );
        }
    }

    private function checkIdempotency(WebhookLog $webhookLog, array $payload): void
    {
        $existingProcessed = WebhookLog::where('transaction_id', $payload['TransactionId'] ?? '')
            ->where('status', 'processed')
            ->where('id', '!=', $webhookLog->id)
            ->exists();

        if ($existingProcessed) {
            $webhookLog->update([
                'status' => 'duplicate',
                'error_message' => 'Transaction already processed',
                'processed_at' => now(),
            ]);

            throw new WebhookEarlyExitException(
                response()->json(['status' => 'ok', 'message' => 'Already processed'])
            );
        }
    }

    private function resolveVirtualAccount(WebhookLog $webhookLog, array $payload): VirtualAccount
    {
        $accountNumber = $payload['AccountNumber'] ?? null;
        $virtualAccount = VirtualAccount::where('account_number', $accountNumber)
            ->where('provider', 'nectapay')
            ->where('is_active', true)
            ->first();

        if (!$virtualAccount) {
            Log::error('NectaPay webhook: Virtual account not found', [
                'account_number' => $accountNumber,
                'webhook_log_id' => $webhookLog->id,
            ]);

            throw new WebhookEarlyExitException(
                $this->failWebhook($webhookLog, "No active virtual account found for account number: {$accountNumber}", 404)
            );
        }

        return $virtualAccount;
    }

    private function processPayment(WebhookLog $webhookLog, $owner, float $amountPaid, array $payload): JsonResponse
    {
        $systemFee = $this->nectaPay->getClient()->getConfig()->systemFee;
        $netAmount = max(0, $amountPaid - $systemFee);

        $metadata = [
            'source' => 'nectapay_webhook',
            'transaction_id' => $payload['TransactionId'] ?? null,
            'account_number' => $payload['AccountNumber'] ?? null,
            'settlement_amount' => $payload['SettlementAmountDue'] ?? null,
            'charges' => $payload['Charges'] ?? null,
        ];

        $payment = null;
        $handlerClass = config('nectapay.payment_handler');

        if ($handlerClass && class_exists($handlerClass)) {
            /** @var PaymentHandler $handler */
            $handler = app($handlerClass);
            $payment = $handler->handleWebhookPayment($owner, $netAmount, $metadata);
        } else {
            Log::info('NectaPay webhook: No payment handler configured, logging only', [
                'owner_id' => $owner->getKey(),
                'net_amount' => $netAmount,
            ]);
        }

        $webhookLog->update([
            'status' => 'processed',
            'payment_id' => $payment?->getKey(),
            'processed_at' => now(),
        ]);

        Log::info('NectaPay webhook: Payment processed', [
            'payment_id' => $payment?->getKey(),
            'owner_id' => $owner->getKey(),
            'amount' => $netAmount,
        ]);

        return response()->json(['status' => 'ok', 'message' => 'Payment processed']);
    }

    private function failWebhook(WebhookLog $webhookLog, string $message, int $statusCode): JsonResponse
    {
        $webhookLog->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        return response()->json(['status' => 'error', 'message' => $message], $statusCode);
    }
}
