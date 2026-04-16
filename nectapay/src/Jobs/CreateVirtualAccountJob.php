<?php

namespace HRC\NectaPay\Jobs;

use HRC\NectaPay\Services\NectaPayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateVirtualAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public Model $owner,
    ) {
        $this->onQueue(config('nectapay.queue', 'default'));
    }

    public function handle(NectaPayService $nectaPay): void
    {
        try {
            $account = $nectaPay->createStaticAccount($this->owner);

            Log::info('Virtual account created', [
                'owner_id' => $this->owner->getKey(),
                'account_number' => $account->account_number,
                'bank_name' => $account->bank_name,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create virtual account', [
                'owner_id' => $this->owner->getKey(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['nectapay', 'virtual-account', "owner:{$this->owner->getKey()}"];
    }
}
