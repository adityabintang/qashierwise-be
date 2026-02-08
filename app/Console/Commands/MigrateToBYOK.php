<?php

namespace App\Console\Commands;

use App\Models\PaymentProviderCredential;
use App\Models\QrisTransaction;
use App\Models\User;
use App\Services\EncryptionService;
use App\Services\KeyManagementService;
use App\Services\ProviderCredentialService;
use App\Services\ProviderValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateToBYOK extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qris:migrate-to-byok 
                            {--user-id= : Migrate specific user by ID}
                            {--all : Migrate all eligible users}
                            {--dry-run : Show what would be migrated without making changes}
                            {--rollback= : Rollback migration for specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing Midtrans QRIS users to BYOK (Bring Your Own Key) system';

    /**
     * Execute the console command.
     */
    public function handle(
        ProviderCredentialService $credentialService,
        ProviderValidationService $validationService,
        EncryptionService $encryptionService,
        KeyManagementService $keyManagementService
    ): int {
        $this->info('🔄 QRIS BYOK Migration Tool');
        $this->newLine();

        // Handle rollback
        if ($this->option('rollback')) {
            return $this->handleRollback((int) $this->option('rollback'));
        }

        // Identify eligible users
        $eligibleUsers = $this->identifyEligibleUsers();

        if ($eligibleUsers->isEmpty()) {
            $this->info('✅ No users need migration. All users are already on BYOK system.');

            return 0;
        }

        $this->info("Found {$eligibleUsers->count()} user(s) eligible for migration:");
        $this->newLine();

        // Display eligible users
        $this->table(
            ['ID', 'Name', 'Email', 'Transactions', 'Sub-Merchant'],
            $eligibleUsers->map(function ($user) {
                return [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->transactions_count,
                    $user->subMerchant ? 'Yes' : 'No',
                ];
            })
        );

        $this->newLine();

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');

            return 0;
        }

        // Migrate specific user
        if ($this->option('user-id')) {
            $userId = (int) $this->option('user-id');
            $user = $eligibleUsers->firstWhere('id', $userId);

            if (! $user) {
                $this->error("❌ User ID {$userId} not found or not eligible for migration");

                return 1;
            }

            return $this->migrateUser($user, $credentialService, $validationService);
        }

        // Migrate all users
        if ($this->option('all')) {
            $this->warn('⚠️  This will migrate ALL eligible users. Each user will need to provide their Midtrans credentials.');

            if (! $this->confirm('Do you want to continue?')) {
                $this->info('Migration cancelled.');

                return 0;
            }

            $successCount = 0;
            $failCount = 0;

            foreach ($eligibleUsers as $user) {
                $result = $this->migrateUser($user, $credentialService, $validationService);

                if ($result === 0) {
                    $successCount++;
                } else {
                    $failCount++;
                }

                $this->newLine();
            }

            $this->info("✅ Migration complete: {$successCount} successful, {$failCount} failed");

            return $failCount > 0 ? 1 : 0;
        }

        // Interactive mode - prompt user to select
        $this->info('💡 Use --user-id=<ID> to migrate a specific user, or --all to migrate all users');

        return 0;
    }

    /**
     * Identify users eligible for migration.
     */
    protected function identifyEligibleUsers()
    {
        return User::query()
            ->whereHas('subMerchant')
            ->whereHas('subMerchant.transactions', function ($query) {
                // Users with existing QRIS transactions
                $query->whereNotNull('midtrans_transaction_id')
                    ->orWhere('provider', 'midtrans');
            })
            ->whereDoesntHave('paymentProviderCredentials', function ($query) {
                // But no BYOK credentials yet
                $query->where('provider', PaymentProviderCredential::PROVIDER_MIDTRANS);
            })
            ->withCount([
                'subMerchant.transactions as transactions_count',
            ])
            ->with('subMerchant')
            ->get();
    }

    /**
     * Migrate a single user to BYOK system.
     */
    protected function migrateUser(
        User $user,
        ProviderCredentialService $credentialService,
        ProviderValidationService $validationService
    ): int {
        $this->info("🔄 Migrating user: {$user->name} ({$user->email})");
        $this->newLine();

        // Prompt for Midtrans credentials
        $this->warn('⚠️  This user needs to provide their own Midtrans API credentials.');
        $this->info('You can obtain these from: https://dashboard.midtrans.com/settings/config_info');
        $this->newLine();

        $serverKey = $this->secret('Enter Midtrans Server Key');

        if (empty($serverKey)) {
            $this->error('❌ Server Key is required. Migration cancelled.');

            return 1;
        }

        $clientKey = $this->ask('Enter Midtrans Client Key');

        if (empty($clientKey)) {
            $this->error('❌ Client Key is required. Migration cancelled.');

            return 1;
        }

        // Validate credentials before migration
        $this->info('🔍 Validating Midtrans credentials...');

        try {
            DB::beginTransaction();

            // Store credentials
            $credential = $credentialService->storeCredentials(
                $user,
                PaymentProviderCredential::PROVIDER_MIDTRANS,
                [
                    'server_key' => $serverKey,
                    'client_key' => $clientKey,
                ]
            );

            // Validate credentials
            $validationResult = $validationService->validateCredentials($credential);

            if (! $validationResult->isValid) {
                DB::rollBack();
                $this->error('❌ Credential validation failed: '.$validationResult->errorMessage);
                $this->error('Please check your credentials and try again.');

                return 1;
            }

            $this->info('✅ Credentials validated successfully');

            // Set as active provider
            $credentialService->setActiveProvider($user, PaymentProviderCredential::PROVIDER_MIDTRANS);

            // Update existing transactions to include provider field
            $this->migrateTransactionHistory($user);

            DB::commit();

            $this->info('✅ Migration completed successfully for '.$user->name);
            $this->info('   - Credentials stored and encrypted');
            $this->info('   - Midtrans set as active provider');
            $this->info('   - Transaction history preserved');

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Migration failed: '.$e->getMessage());
            $this->error('   All changes have been rolled back.');

            return 1;
        }
    }

    /**
     * Migrate transaction history to include provider information.
     */
    protected function migrateTransactionHistory(User $user): void
    {
        $subMerchant = $user->subMerchant;

        if (! $subMerchant) {
            return;
        }

        // Update transactions that don't have provider set
        $updated = QrisTransaction::where('sub_merchant_id', $subMerchant->id)
            ->whereNull('provider')
            ->update([
                'provider' => PaymentProviderCredential::PROVIDER_MIDTRANS,
                'provider_transaction_id' => DB::raw('midtrans_transaction_id'),
            ]);

        if ($updated > 0) {
            $this->info("   - Updated {$updated} transaction(s) with provider information");
        }
    }

    /**
     * Handle rollback of a migration.
     */
    protected function handleRollback(int $userId): int
    {
        $this->warn("🔄 Rolling back migration for user ID: {$userId}");
        $this->newLine();

        $user = User::find($userId);

        if (! $user) {
            $this->error("❌ User ID {$userId} not found");

            return 1;
        }

        if (! $this->confirm("Are you sure you want to rollback migration for {$user->name}?")) {
            $this->info('Rollback cancelled.');

            return 0;
        }

        try {
            DB::beginTransaction();

            // Delete BYOK credentials
            $deleted = PaymentProviderCredential::where('user_id', $userId)
                ->where('provider', PaymentProviderCredential::PROVIDER_MIDTRANS)
                ->delete();

            // Optionally clear provider field from transactions
            // (keeping it for historical purposes by default)

            DB::commit();

            $this->info('✅ Rollback completed');
            $this->info("   - Deleted {$deleted} credential record(s)");
            $this->info('   - Transaction history preserved');
            $this->warn('   Note: User will need to reconfigure credentials to use QRIS');

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Rollback failed: '.$e->getMessage());

            return 1;
        }
    }
}
