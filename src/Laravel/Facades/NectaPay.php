<?php

namespace HRC\NectaPay\Laravel\Facades;

use HRC\NectaPay\Laravel\NectaPayService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string getAuthToken()
 * @method static \HRC\NectaPay\Laravel\Models\VirtualAccount createStaticAccount(\Illuminate\Database\Eloquent\Model $owner)
 * @method static array createBatchAccounts(\Illuminate\Support\Collection $owners)
 * @method static array verifyTransaction(string $transactionId)
 * @method static array retrieveAccount(string $paymentRef)
 * @method static bool validateWebhookHash(array $payload)
 * @method static void clearAuthToken()
 *
 * @see \HRC\NectaPay\Laravel\NectaPayService
 */
class NectaPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NectaPayService::class;
    }
}
