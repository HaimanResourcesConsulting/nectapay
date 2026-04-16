# Laravel NectaPay by [HRC](https://haimanresources.com)

A reusable Laravel package for NectaPay virtual account provisioning, webhook handling, and payment processing.

## Installation

### From a local path (during development)

Add to your project's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/nectapay"
    }
],
"require": {
    "hrc/laravel-nectapay": "*"
}
```

Then run:

```bash
composer update hrc/laravel-nectapay
```

### Publish config and migrations

```bash
php artisan vendor:publish --tag=nectapay-config
php artisan vendor:publish --tag=nectapay-migrations
php artisan migrate
```

## Configuration

Add these to your `.env`:

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
    return $this->hasOne(\HRC\NectaPay\Models\VirtualAccount::class, 'owner_id');
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

## Payment Handler

To process payments from webhooks, implement the `PaymentHandler` contract:

```php
use HRC\NectaPay\Contracts\PaymentHandler;
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

Set it in your config or `.env`:

```env
NECTAPAY_PAYMENT_HANDLER=App\Services\MyPaymentHandler
```

## Usage

### Provision a single account

```php
use HRC\NectaPay\Facades\NectaPay;

$account = NectaPay::createStaticAccount($owner);
```

### Dispatch async job

```php
use HRC\NectaPay\Jobs\CreateVirtualAccountJob;

CreateVirtualAccountJob::dispatch($owner);
```

### Artisan command

```bash
# Provision for a specific owner
php artisan nectapay:provision-accounts --owner=000100500

# Batch provision all active owners without accounts
php artisan nectapay:provision-accounts

# Dry run
php artisan nectapay:provision-accounts --dry-run
```

### Verify a transaction

```php
$result = NectaPay::verifyTransaction($transactionId);
```

## Package Structure

```
packages/nectapay/
├── composer.json
├── config/
│   └── nectapay.php
├── database/migrations/
│   ├── create_nectapay_virtual_accounts_table.php
│   └── create_nectapay_webhook_logs_table.php
├── routes/
│   └── webhook.php
└── src/
    ├── NectaPayServiceProvider.php
    ├── Console/
    │   └── ProvisionVirtualAccountsCommand.php
    ├── Contracts/
    │   └── PaymentHandler.php
    ├── Exceptions/
    │   ├── NectaPayException.php
    │   └── WebhookEarlyExitException.php
    ├── Facades/
    │   └── NectaPay.php
    ├── Http/Controllers/
    │   └── NectaPayWebhookController.php
    ├── Jobs/
    │   └── CreateVirtualAccountJob.php
    ├── Models/
    │   ├── VirtualAccount.php
    │   └── WebhookLog.php
    └── Services/
        └── NectaPayService.php
```
