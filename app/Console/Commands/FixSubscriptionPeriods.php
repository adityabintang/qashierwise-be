<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixSubscriptionPeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:fix-periods {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix subscription period_end dates that have overflow issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Running in DRY RUN mode - no changes will be made');
        }
        
        $this->info('=== Fixing Subscription Period Dates ===');
        $this->newLine();

        // Ambil semua subscription yang aktif atau cancelled
        $subscriptions = Subscription::whereIn('status', ['active', 'cancelled'])
            ->whereNotNull('current_period_start')
            ->whereNotNull('current_period_end')
            ->get();

        $this->info("Found {$subscriptions->count()} subscriptions to check");
        $this->newLine();

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($subscriptions->count());
        $progressBar->start();

        foreach ($subscriptions as $subscription) {
            try {
                $start = Carbon::parse($subscription->current_period_start);
                $end = Carbon::parse($subscription->current_period_end);
                
                // Hitung berapa bulan seharusnya dari metadata
                $metadata = $subscription->metadata ? json_decode($subscription->metadata, true) : [];
                $months = $metadata['months'] ?? 1;
                
                // Hitung period_end yang benar menggunakan addMonthsNoOverflow
                $correctEnd = $start->copy()->addMonthsNoOverflow($months);
                
                // Cek apakah ada perbedaan
                if (!$end->isSameDay($correctEnd)) {
                    $this->newLine();
                    $this->warn("Subscription ID: {$subscription->id}");
                    $this->line("  User ID: {$subscription->user_id}");
                    $this->line("  Plan: {$subscription->plan_name}");
                    $this->line("  Start: {$start->format('Y-m-d')}");
                    $this->line("  End (incorrect): {$end->format('Y-m-d')}");
                    $this->line("  End (correct): {$correctEnd->format('Y-m-d')}");
                    
                    if (!$dryRun) {
                        // Update ke database
                        $subscription->current_period_end = $correctEnd;
                        $subscription->save();
                        $this->info("  ✓ Fixed!");
                    } else {
                        $this->comment("  → Would be fixed (dry-run mode)");
                    }
                    
                    $this->newLine();
                    $fixed++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing subscription {$subscription->id}: {$e->getMessage()}");
                $errors++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('=== Summary ===');
        
        if ($dryRun) {
            $this->comment("Would fix: {$fixed} subscriptions");
        } else {
            $this->info("Fixed: {$fixed} subscriptions");
        }
        
        $this->line("Already correct: {$skipped} subscriptions");
        
        if ($errors > 0) {
            $this->error("Errors: {$errors}");
        }

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->comment('Run without --dry-run to apply changes');
        }

        return self::SUCCESS;
    }
}
