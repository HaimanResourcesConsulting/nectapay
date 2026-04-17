<?php

namespace HRC\NectaPay\Laravel\Console;

use HRC\NectaPay\Laravel\Models\VirtualAccount;
use HRC\NectaPay\Laravel\NectaPayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProvisionVirtualAccountsCommand extends Command
{
    protected $signature = 'nectapay:provision-accounts
                            {--batch-size=20 : Number of accounts to create per batch}
                            {--owner= : Provision for a specific owner ID only}
                            {--dry-run : Show what would be provisioned without making API calls}';

    protected $description = 'Provision NectaPay virtual accounts for active owners who do not have one';

    public function handle(NectaPayService $nectaPay): int
    {
        if ($ownerId = $this->option('owner')) {
            return $this->provisionSingle($nectaPay, $ownerId);
        }

        return $this->provisionBatch($nectaPay);
    }

    private function provisionSingle(NectaPayService $nectaPay, string $ownerId): int
    {
        $ownerModel = config('nectapay.owner_model');
        $idAttribute = config('nectapay.owner_id_attribute');
        $query = $ownerModel::query();

        if ($idAttribute) {
            $query->where($idAttribute, $ownerId);
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ownerId)) {
            $query->orWhere('id', $ownerId);
        } elseif (!$idAttribute) {
            $query->where('id', $ownerId);
        }

        $owner = $query->first();

        if (!$owner) {
            $this->error("Owner not found: {$ownerId}");
            return self::FAILURE;
        }

        $existingAccount = VirtualAccount::where('owner_id', $owner->getKey())
            ->where('provider', 'nectapay')
            ->first();

        if ($existingAccount?->account_number) {
            $this->info("Owner {$ownerId} already has a virtual account: {$existingAccount->account_number}");
        } elseif ($this->option('dry-run')) {
            $this->info("[DRY RUN] Would provision: {$owner->name} ({$ownerId})");
        } else {
            try {
                $account = $nectaPay->createStaticAccount($owner);
                $this->info("Created virtual account for {$owner->name}: {$account->account_number} ({$account->bank_name})");
            } catch (\Throwable $e) {
                $this->error("Failed: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function provisionBatch(NectaPayService $nectaPay): int
    {
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $ownerModel = config('nectapay.owner_model');

        $query = $ownerModel::query();
        if (method_exists($ownerModel, 'scopeActive')) {
            $query->active();
        }
        $query->whereDoesntHave('virtualAccount', fn ($q) => $q->where('provider', 'nectapay'));

        $total = $query->count();

        if ($total === 0) {
            $this->info('All owners already have virtual accounts.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} owners without virtual accounts.");

        if ($dryRun || !$this->confirm("Provision {$total} virtual accounts in batches of {$batchSize}?")) {
            if ($dryRun) {
                $this->showDryRun($query, $total);
            }
            return self::SUCCESS;
        }

        [$created, $failed] = $this->executeProvisioning($nectaPay, $query, $total, $batchSize);

        $this->info("Provisioning complete: {$created} created, {$failed} failed.");

        if ($failed > 0) {
            $this->warn('Check logs for failed provisioning details.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function showDryRun($query, int $total): void
    {
        $query->take(50)->get()->each(function ($owner) {
            $id = $owner->getKey();
            $this->line("  [DRY RUN] {$owner->name} ({$id})");
        });
        if ($total > 50) {
            $this->line("  ... and " . ($total - 50) . " more");
        }
    }

    private function executeProvisioning(NectaPayService $nectaPay, $query, int $total, int $batchSize): array
    {
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $failed = 0;

        $query->chunk($batchSize, function ($owners) use ($nectaPay, &$created, &$failed, $bar) {
            try {
                if ($owners->count() > 1) {
                    $accounts = $nectaPay->createBatchAccounts($owners);
                    $created += count($accounts);
                } else {
                    $owner = $owners->first();
                    $nectaPay->createStaticAccount($owner);
                    $created++;
                }
            } catch (\Throwable $e) {
                foreach ($owners as $owner) {
                    try {
                        $nectaPay->createStaticAccount($owner);
                        $created++;
                    } catch (\Throwable $inner) {
                        $failed++;
                        Log::error('NectaPay provision failed', [
                            'owner_id' => $owner->getKey(),
                            'error' => $inner->getMessage(),
                        ]);
                    }
                }
            }

            $bar->advance($owners->count());
            usleep(500_000);
        });

        $bar->finish();
        $this->newLine(2);

        return [$created, $failed];
    }
}
