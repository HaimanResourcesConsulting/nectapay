# NectaPay PHP SDK by [HRC](https://haimanresources.com)

A framework-agnostic PHP package by [Haiman Resources Consulting](https://haimanresources.com) for NectaPay virtual account provisioning, webhook handling, and payment processing. Works with Laravel, Symfony, CodeIgniter, native PHP, and any PHP framework..

## Installation

```bash
composer require hrc/nectapay
```

### For standalone / native PHP (with Guzzle)

```bash
composer require hrc/nectapay guzzlehttp/guzzle
```

### For Laravel

Laravel auto-discovers the service provider. Then publish config and migrations:

```bash
php artisan vendor:publish --tag=nectapay-config
php artisan vendor:publish --tag=nectapay-migrations
php artisan migrate
```

## Configuration

### Environment Variables

```env
NECTAPAY_BASE_URL=https://demo.nectapay.com/api/
NECTAPAY_API_KEY=your-api-key
NECTAPAY_MERCHANT_ID=your-merchant-id
NECTAPAY_WEBHOOK_SECRET=your-webhook-secret
NECTAPAY_SYSTEM_FEE=200
NECTAPAY_ACCOUNT_PREFIX=MYAPP
```

---

## Usage: Any PHP Framework / Native PHP

The core `NectaPayClient` is completely framework-agnostic. It depends only on simple interfaces you can implement with any HTTP client, cache, or logger.

### Quick Start

```php
use HRC\NectaPay\Config;
use HRC\NectaPay\NectaPayClient;
use HRC\NectaPay\Http\GuzzleHttpClient;
use HRC\NectaPay\Cache\InMemoryCache;

// 1. Create config
$config = new Config(
    baseUrl: 'https://demo.nectapay.com/api',
    apiKey: 'your-api-key',
    merchantId: 'your-merchant-id',
    webhookSecret: 'your-webhook-secret',
    systemFee: 200,
    accountPrefix: 'MYAPP',
);

// Or from an array:
// $config = Config::fromArray($configArray);

// 2. Create client
$client = new NectaPayClient(
    config: $config,
    httpClient: new GuzzleHttpClient(),
    cache: new InMemoryCache(),
    // logger: $anyPsr3Logger,  // optional PSR-3 logger
);

// 3. Use it

// Initiate a dynamic virtual account for a single transaction
$transfer = $client->initiateTransfer(1200.00, 'tx_unique_123', 'Order #123');
// Returns: account_number, bank_name, expires_in_minutes, etc.

$account = $client->createStaticAccount('John Doe - MYAPP', 'myapp_owner_123');
$result = $client->verifyTransaction('TXN_123456');
$isValid = $client->validateWebhookHash($webhookPayload);
```

### Custom HTTP Client

Implement `HttpClientInterface` to use any HTTP library (cURL, Symfony HttpClient, etc.):

```php
use HRC\NectaPay\Contracts\HttpClientInterface;

class MyHttpClient implements HttpClientInterface
{
    public function get(string $url, array $headers = []): array
    {
        // Your HTTP GET implementation
        return ['status' => 200, 'body' => $decodedJson];
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        // Your HTTP POST implementation
        return ['status' => 200, 'body' => $decodedJson];
    }
}
```

### Custom Cache

Implement `CacheInterface` to use Redis, Memcached, file cache, or any backend:

```php
use HRC\NectaPay\Contracts\CacheInterface;

class RedisCacheAdapter implements CacheInterface
{
    public function get(string $key): ?string { /* ... */ }
    public function set(string $key, string $value, int $ttlSeconds): void { /* ... */ }
    public function forget(string $key): void { /* ... */ }
}
```

### Webhook Validation (Any Framework)

```php
// In your webhook endpoint handler:
$payload = json_decode(file_get_contents('php://input'), true);

if ($client->validateWebhookHash($payload)) {
    // Process the payment...
    $data = $payload['data'] ?? $payload;
    $accountNumber = $data['AccountNumber'];
    $amountPaid = (float) $data['AmountPaid'];
    $transactionId = $data['TransactionId'];
}
```

### Payment Handler (Generic)

Implement `PaymentHandlerInterface` for framework-agnostic payment processing:

```php
use HRC\NectaPay\Contracts\PaymentHandlerInterface;

class MyPaymentHandler implements PaymentHandlerInterface
{
    public function handlePayment(string $ownerId, float $amount, array $metadata): mixed
    {
        // Record payment in your database, generate receipt, etc.
        return $paymentRecord;
    }
}
```

---

## Usage: Laravel

Laravel users get auto-wired services, Eloquent models, queued jobs, Facade, and artisan commands out of the box.

### Laravel Configuration

Add to your `.env`:

```env
NECTAPAY_BASE_URL=https://demo.nectapay.com/api/
NECTAPAY_API_KEY=your-api-key
NECTAPAY_MERCHANT_ID=your-merchant-id
NECTAPAY_WEBHOOK_SECRET=your-webhook-secret
NECTAPAY_SYSTEM_FEE=200
NECTAPAY_ACCOUNT_PREFIX=MYAPP
NECTAPAY_OWNER_MODEL=App\Models\Student
NECTAPAY_OWNER_ID_ATTRIBUTE=student_id
```

### Owner Model Requirements

The owner model (student, customer, user, etc.) must have:
- A UUID primary key (`id`)
- A `name` accessor or attribute
- Optionally, a unique identifier attribute (configured via `NECTAPAY_OWNER_ID_ATTRIBUTE`)
- A `virtualAccount` relationship (add this to your model):

```php
public function virtualAccount()
{
    return $this->hasOne(\HRC\NectaPay\Laravel\Models\VirtualAccount::class, 'owner_id');
}
```

### Webhook CSRF Exemption

Exclude the webhook path from CSRF verification in your `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhook/nectapay',
    ]);
})
```

### Laravel Payment Handler

Implement the Laravel-specific `PaymentHandler` contract:

```php
use HRC\NectaPay\Laravel\Contracts\PaymentHandler;
use Illuminate\Database\Eloquent\Model;

class MyPaymentHandler implements PaymentHandler
{
    public function handleWebhookPayment(Model $owner, float $amount, array $metadata): ?Model
    {
        // Record payment, allocate to invoices, generate receipt, etc.
        return $payment;
    }
}
```

Set it in your `.env`:

```env
NECTAPAY_PAYMENT_HANDLER=App\Services\MyPaymentHandler
```

### Provision a Single Account (Laravel)

```php
use HRC\NectaPay\Laravel\Facades\NectaPay;

$account = NectaPay::createStaticAccount($owner);
```

### Dispatch Async Job (Laravel)

```php
use HRC\NectaPay\Laravel\Jobs\CreateVirtualAccountJob;

CreateVirtualAccountJob::dispatch($owner);
```

### Artisan Command

```bash
# Provision for a specific owner
php artisan nectapay:provision-accounts --owner=000100500

# Batch provision all active owners without accounts
php artisan nectapay:provision-accounts

# Dry run
php artisan nectapay:provision-accounts --dry-run
```

### Initiate a Dynamic Transfer (Laravel)

```php
$transfer = NectaPay::initiateTransfer(1200.00, 'tx_unique_123', 'Order #123');
// $transfer['account_number']    — temporary account to pay into
// $transfer['bank_name']         — bank name
// $transfer['expires_in_minutes'] — time before the account expires
```

### Verify a Transaction (Laravel)

```php
$result = NectaPay::verifyTransaction($transactionId);
```

---

## Architecture

The package is split into a **framework-agnostic core** and **framework-specific adapters**:

```
src/
├── Config.php                          ← Configuration value object
├── NectaPayClient.php                  ← Core API client (no framework deps)
├── Contracts/
│   ├── HttpClientInterface.php         ← HTTP abstraction
│   ├── CacheInterface.php              ← Cache abstraction
│   ├── PaymentHandlerInterface.php     ← Generic payment handler
│   └── OwnerInterface.php              ← Owner model contract
├── DTOs/
│   ├── VirtualAccountData.php          ← Virtual account data transfer object
│   └── WebhookResult.php               ← Webhook processing result
├── Http/
│   └── GuzzleHttpClient.php            ← Guzzle HTTP implementation
├── Cache/
│   └── InMemoryCache.php               ← Simple in-memory cache
├── Exceptions/
│   └── NectaPayException.php           ← Framework-agnostic exceptions
└── Laravel/                            ← Laravel-specific adapter
    ├── NectaPayServiceProvider.php
    ├── NectaPayService.php             ← Eloquent-aware service wrapper
    ├── Facades/NectaPay.php
    ├── Contracts/PaymentHandler.php
    ├── Models/
    │   ├── VirtualAccount.php
    │   └── WebhookLog.php
    ├── Console/ProvisionVirtualAccountsCommand.php
    ├── Http/
    │   ├── LaravelHttpClient.php
    │   └── Controllers/NectaPayWebhookController.php
    ├── Jobs/CreateVirtualAccountJob.php
    ├── Cache/LaravelCacheAdapter.php
    └── Exceptions/WebhookEarlyExitException.php
```

### Core Dependencies

| Interface | Purpose | Bundled Implementations |
|-----------|---------|------------------------|
| `HttpClientInterface` | HTTP requests | `GuzzleHttpClient`, `LaravelHttpClient` |
| `CacheInterface` | Auth token caching | `InMemoryCache`, `LaravelCacheAdapter` |
| `PSR-3 LoggerInterface` | Logging (optional) | Any PSR-3 logger |
