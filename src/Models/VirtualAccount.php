<?php

namespace HRC\NectaPay\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualAccount extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nectapay_virtual_accounts';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'provider',
        'account_name',
        'account_number',
        'bank_name',
        'payment_ref',
        'provider_id',
        'is_active',
        'metadata',
        'provider_response',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'provider_response' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('nectapay.owner_model'), 'owner_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProvider($query, string $provider = 'nectapay')
    {
        return $query->where('provider', $provider);
    }
}
