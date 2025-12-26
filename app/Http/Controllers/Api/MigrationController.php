<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderCredential;
use App\Models\QrisTransaction;
use App\Services\ProviderCredentialService;
use App\Services\ProviderValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrationController extends Controller
{
    public function __construct(
        private ProviderCredentialService $credentialService,
        private ProviderValidationService $validationService
    ) {}

    /**
     * Check if the current user needs migration.
     */
    public function checkMigrationStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has sub-merchant account
        if (!$user->subMerchant) {
            return response()->json([
                'needs_migration' => false,
                'reason' => 'not_sub_merchant',
            ]);
        }

        // Check if user already has BYOK credentials
        $hasCredentials = PaymentProviderCredential::where('user_id', $user->id)
            ->exists();

        if ($hasCredentials) {
            return response()->json([
                'needs_migration' => false,
                'reason' => 'already_migrated',
            ]);
        }

        // Check if user has existing transactions
        $transactionCount = QrisTransaction::where('sub_merchant_id', $user->subMerchant->id)
            ->where(function ($query) {
                $query->whereNotNull('midtrans_transaction_id')
                    ->orWhere('provider', 'midtrans');
            })
            ->count();

        if ($transactionCount === 0) {
            return response()->json([
                'needs_migration' => false,
                'reason' => 'no_transactions',
            ]);
        }

        return response()->json([
            'needs_migration' => true,
            'transaction_count' => $transactionCount,
            'message' => 'You need to configure your own Midtrans credentials to continue using QRIS.',
        ]);
    }

    /**
     * Perform the migration with user-provided credentials.
     */
    public function migrate(Request $request): JsonResponse
    {
        $request->validate([
            'server_key' => 'required|string',
            'client_key' => 'required|string',
        ]);

        $user = $request->user();

        // Verify user needs migration
        if (!$user->subMerchant) {
            return response()->json([
                'success' => false,
                'message' => 'You are not registered as a sub-merchant.',
            ], 400);
        }

        // Check if already migrated
        $existingCredential = PaymentProviderCredential::where('user_id', $user->id)
            ->where('provider', PaymentProviderCredential::PROVIDER_MIDTRANS)
            ->first();

        if ($existingCredential) {
            return response()->json([
                'success' => false,
                'message' => 'You have already migrated to BYOK system.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Store credentials
            $credential = $this->credentialService->storeCredentials(
                $user,
                PaymentProviderCredential::PROVIDER_MIDTRANS,
                [
                    'server_key' => $request->server_key,
                    'client_key' => $request->client_key,
                ]
            );

            // Validate credentials
            $validationResult = $this->validationService->validateCredentials($credential);

            if (!$validationResult->isValid) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Credential validation failed: ' . $validationResult->errorMessage,
                    'validation_error' => $validationResult->errorMessage,
                ], 422);
            }

            // Set as active provider
            $this->credentialService->setActiveProvider($user, PaymentProviderCredential::PROVIDER_MIDTRANS);

            // Update existing transactions
            $updatedCount = $this->migrateTransactionHistory($user);

            DB::commit();

            Log::info('User migrated to BYOK', [
                'user_id' => $user->id,
                'transactions_updated' => $updatedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Migration completed successfully!',
                'transactions_updated' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Migration failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Migration failed. Please try again or contact support.',
            ], 500);
        }
    }

    /**
     * Skip migration (user will be prompted again later).
     */
    public function skipMigration(Request $request): JsonResponse
    {
        // In a real implementation, you might want to track when users skip
        // and how many times, to avoid annoying them too much
        
        return response()->json([
            'success' => true,
            'message' => 'Migration skipped. You can migrate later from provider settings.',
        ]);
    }

    /**
     * Migrate transaction history to include provider information.
     */
    private function migrateTransactionHistory($user): int
    {
        $subMerchant = $user->subMerchant;

        if (!$subMerchant) {
            return 0;
        }

        // Update transactions that don't have provider set
        return QrisTransaction::where('sub_merchant_id', $subMerchant->id)
            ->whereNull('provider')
            ->update([
                'provider' => PaymentProviderCredential::PROVIDER_MIDTRANS,
                'provider_transaction_id' => DB::raw('midtrans_transaction_id'),
            ]);
    }
}
