<?php

namespace App\Console\Commands;

use App\Models\SubMerchant;
use App\Services\XenPlatformService;
use Illuminate\Console\Command;

class ProvisionXenPlatformAccounts extends Command
{
    protected $signature = 'xenplatform:provision {--merchant-id= : Specific sub-merchant ID to provision}';

    protected $description = 'Create XenPlatform sub-accounts for existing merchants that do not have one';

    public function handle(XenPlatformService $xenPlatformService): int
    {
        $query = SubMerchant::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('xendit_account_id')
                    ->orWhereIn('xendit_account_status', ['pending', 'failed']);
            });

        if ($merchantId = $this->option('merchant-id')) {
            $query->where('id', $merchantId);
        }

        $merchants = $query->get();

        if ($merchants->isEmpty()) {
            $this->info('No merchants need provisioning.');

            return self::SUCCESS;
        }

        $this->info("Found {$merchants->count()} merchant(s) to provision.");

        $success = 0;
        $failed = 0;

        foreach ($merchants as $merchant) {
            $this->line("Processing: {$merchant->business_name} (ID: {$merchant->id})...");

            try {
                $result = $xenPlatformService->createSubAccount($merchant);

                $merchant->update([
                    'xendit_account_id' => $result['id'],
                    'xendit_account_status' => 'active',
                ]);

                $this->info("  -> Created XenPlatform account: {$result['id']}");
                $success++;
            } catch (\Exception $e) {
                $merchant->update(['xendit_account_status' => 'failed']);
                $this->error("  -> Failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Success: {$success}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
