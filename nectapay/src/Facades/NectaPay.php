<?php

namespace HRC\NectaPay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getAuthToken()
 * @method static \HRC\NectaPay\Models\VirtualAccount createStaticAccount(\Illuminate\Database\Eloquent\Model $owner)
 * @method static array createBatchAccounts(\Illuminate\Support\Collection $owners)
 * @method static array verifyTransaction(string $transactionId)
 * @method static array retrieveAccount(string $paymentRef)
 * @method static bool validateWebhookHash(array $payload)
 * @method static void clearAuthToken()
 *
 * @see \HRC\NectaPay\Services\NectaPayService
 */
class NectaPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HRC\NectaPay\Services\NectaPayService::class;
    }
}
