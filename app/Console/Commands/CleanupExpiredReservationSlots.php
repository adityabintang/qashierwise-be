<?php

namespace App\Console\Commands;

use App\Models\ReservationConfig;
use App\Services\ReservationSlotGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredReservationSlots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:cleanup-expired-slots
                            {--dry-run : Perform a dry run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired reservation slots from reservation configs';

    /**
     * Execute the console command.
     */
    public function handle(ReservationSlotGenerator $generator): int
    {
        $this->info('Starting cleanup of expired reservation slots...');

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be made.');
        }

        // Get all active reservation configs with auto_cleanup_enabled
        $configs = ReservationConfig::where('is_active', true)
            ->where('auto_cleanup_enabled', true)
            ->whereNotNull('available_slots')
            ->get();

        if ($configs->isEmpty()) {
            $this->info('No reservation configs found with auto cleanup enabled.');

            return Command::SUCCESS;
        }

        $this->info("Found {$configs->count()} reservation config(s) with auto cleanup enabled.");

        $processed = 0;
        $totalRemoved = 0;

        foreach ($configs as $config) {
            $this->line("Processing config ID: {$config->id} (User: {$config->user_id}, Store: {$config->store_id})");

            $slots = $config->available_slots;
            $metadata = $config->available_slots_metadata ?? null;

            if (empty($slots)) {
                $this->line('  - No slots to process.');
                continue;
            }

            // Get today's date in the configured timezone
            $timezone = 'Asia/Jakarta';
            $today = Carbon::now($timezone)->startOfDay();

            // Count slots that will be removed
            $slotsToRemove = collect($slots)->filter(function ($slot) use ($today, $timezone) {
                $slotDate = Carbon::parse($slot['datetime'] ?? '', $timezone)->startOfDay();

                return $slotDate->lt($today);
            })->count();

            if ($slotsToRemove === 0) {
                $this->line("  - No expired slots to remove (current date: {$today->toDateString()}).");

                continue;
            }

            $this->line("  - Found {$slotsToRemove} expired slot(s) to remove.");

            if (! $dryRun) {
                // Use the generator to clean up expired slots
                $result = $generator->cleanupExpiredSlots($slots, $metadata);

                // Update the config with cleaned slots and metadata
                $config->update([
                    'available_slots' => $result['slots'],
                    'available_slots_metadata' => $result['metadata'],
                ]);

                Log::info('Cleaned up expired reservation slots', [
                    'config_id' => $config->id,
                    'user_id' => $config->user_id,
                    'store_id' => $config->store_id,
                    'removed_count' => $slotsToRemove,
                    'remaining_count' => count($result['slots']),
                ]);
            }

            $totalRemoved += $slotsToRemove;
            $processed++;
        }

        $this->info("Cleanup complete. Processed {$processed} config(s), removed {$totalRemoved} slot(s).");

        if ($dryRun) {
            $this->warn('DRY-RUN complete. No actual changes were made.');
        }

        return Command::SUCCESS;
    }
}
