<?php

namespace App\Console\Commands;

use App\Models\SubMerchant;
use App\Services\XenPlatformService;
use Illuminate\Console\Command;

/**
 * Backfill callback URLs on every existing XenPlatform sub-account.
 *
 * Sub-accounts do not inherit the master account webhook URLs — each one
 * must have its own callback registered. New sub-accounts get this on
 * creation; this command catches the ones created before that wiring landed.
 */
class SyncXenditSubAccountWebhooks extends Command
{
    protected $signature = 'xendit:sync-webhooks
                            {--account= : Set webhook only for this xendit_account_id}
                            {--dry-run : Show what would happen without calling Xendit}';

    protected $description = 'Register the public callback URL on every Xendit sub-account.';

    public function handle(XenPlatformService $service): int
    {
        $url = config('xendit.webhook_url');
        $this->line("Callback URL : <fg=cyan>{$url}</>");
        if (! $url || ! str_starts_with($url, 'https://')) {
            $this->error('XENDIT_WEBHOOK_URL belum diset atau bukan HTTPS.');
            return self::FAILURE;
        }

        $query = SubMerchant::whereNotNull('xendit_account_id');
        if ($only = $this->option('account')) {
            $query->where('xendit_account_id', $only);
        }
        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('Tidak ada sub-account ditemukan.');
            return self::SUCCESS;
        }

        $this->info("Akan memproses {$accounts->count()} sub-account.");

        $okCount = 0;
        $failCount = 0;

        foreach ($accounts as $sub) {
            $line = sprintf('  [#%d] %s — %s', $sub->id, $sub->business_name ?? '-', $sub->xendit_account_id);
            if ($this->option('dry-run')) {
                $this->line("$line  <fg=yellow>(dry-run, skipped)</>");
                continue;
            }

            try {
                $results = $service->setupSubAccountWebhooks($sub->xendit_account_id);
                $allOk = ! in_array(false, $results, true);
                $summary = collect($results)
                    ->map(fn ($ok, $type) => ($ok ? '✓' : '✗') . $type)
                    ->implode(' ');
                if ($allOk) {
                    $this->line("$line  <fg=green>{$summary}</>");
                    $okCount++;
                } else {
                    $this->line("$line  <fg=red>{$summary}</>");
                    $failCount++;
                }
            } catch (\Throwable $e) {
                $this->line("$line  <fg=red>EXCEPTION: {$e->getMessage()}</>");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Done. ✓ {$okCount}   ✗ {$failCount}");

        return $failCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
