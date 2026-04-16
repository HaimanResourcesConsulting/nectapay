<?php

namespace HRC\NectaPay\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nectapay_webhook_logs';

    protected $fillable = [
        'transaction_id',
        'account_number',
        'payment_ref',
        'amount_paid',
        'status',
        'payload',
        'raw_payload',
        'amounts',
        'hash_details',
        'error_message',
        'payment_id',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payload' => 'array',
        'raw_payload' => 'array',
        'amounts' => 'array',
        'hash_details' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereIn('status', ['received', 'failed']);
    }
}
