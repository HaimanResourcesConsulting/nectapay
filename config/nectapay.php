<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NectaPay API Credentials
    |--------------------------------------------------------------------------
    */
    'base_url' => env('NECTAPAY_BASE_URL', 'https://demo.nectapay.com/api/'),
    'api_key' => env('NECTAPAY_API_KEY'),
    'merchant_id' => env('NECTAPAY_MERCHANT_ID'),
    'webhook_secret' => env('NECTAPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | System Fee
    |--------------------------------------------------------------------------
    | Amount deducted from each incoming payment before recording.
    */
    'system_fee' => env('NECTAPAY_SYSTEM_FEE', 200),

    /*
    |--------------------------------------------------------------------------
    | Account Name Prefix
    |--------------------------------------------------------------------------
    | Prefix used when creating virtual account names (e.g. school short name).
    */
    'account_prefix' => env('NECTAPAY_ACCOUNT_PREFIX', 'APP'),

    /*
    |--------------------------------------------------------------------------
    | Owner Model
    |--------------------------------------------------------------------------
    | The fully qualified class name of the model that owns virtual accounts.
    | This could be a Student, Customer, User, or any other model. Must have:
    |   - 'id' (UUID primary key)
    |   - A unique identifier attribute (configurable via owner_id_attribute)
    |   - A 'name' accessor or attribute
    */
    'owner_model' => env('NECTAPAY_OWNER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Owner ID Attribute
    |--------------------------------------------------------------------------
    | The attribute on the owner model used as a human-readable identifier
    | for payment references. Falls back to the model's primary key.
    */
    'owner_id_attribute' => env('NECTAPAY_OWNER_ID_ATTRIBUTE', null),

    /*
    |--------------------------------------------------------------------------
    | Payment Handler
    |--------------------------------------------------------------------------
    | Class that implements HRC\NectaPay\Contracts\PaymentHandler.
    | This is called by the webhook controller to record and confirm payments.
    | Set to null to use the default (logs only, no payment recording).
    */
    'payment_handler' => env('NECTAPAY_PAYMENT_HANDLER'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    */
    'webhook_path' => env('NECTAPAY_WEBHOOK_PATH', 'webhook/nectapay'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Queue name for async virtual account creation jobs.
    */
    'queue' => env('NECTAPAY_QUEUE', 'default'),
];
