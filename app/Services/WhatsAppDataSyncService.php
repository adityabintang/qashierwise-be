<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppDataSharingConsent;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppDataSyncService
{
    protected EmbeddedSignupService $embeddedSignupService;

    public function __construct(EmbeddedSignupService $embeddedSignupService)
    {
        $this->embeddedSignupService = $embeddedSignupService;
    }

    /**
     * @return array{contacts:int,messages:int,status:string}
     */
    public function syncByConsentId(int $consentId): array
    {
        $consent = WhatsAppDataSharingConsent::query()->findOrFail($consentId);
        $account = WhatsAppAccount::withoutGlobalScope('userAccounts')->findOrFail($consent->whatsapp_account_id);

        if ($consent->status !== WhatsAppDataSharingConsent::STATUS_ACTIVE) {
            $this->markAccountStatus($account, 'idle', 'Consent is not active.');

            return [
                'contacts' => 0,
                'messages' => 0,
                'status' => 'idle',
            ];
        }

        $this->markAccountStatus($account, 'syncing');

        try {
            $this->syncBusinessProfile($account, $consent);

            $selectedContactIds = [];
            if ($consent->sharing_mode === WhatsAppDataSharingConsent::SHARING_MODE_SELECTED) {
                $selectedContactIds = $consent->selected_contact_ids ?? [];
            }

            $contactsCount = $this->calculateContactCount($consent, $account, $selectedContactIds);
            $messageCount = $this->calculateMessageCount($consent, $account, $selectedContactIds);

            $consent->fill([
                'synced_contact_count' => $contactsCount,
                'synced_message_count' => $messageCount,
                'synced_at' => now(),
            ]);
            $consent->save();

            $this->markAccountStatus($account, 'completed');

            return [
                'contacts' => $contactsCount,
                'messages' => $messageCount,
                'status' => 'completed',
            ];
        } catch (\Throwable $exception) {
            $this->markAccountStatus($account, 'failed', $exception->getMessage());

            Log::error('WhatsApp data sync failed', [
                'consent_id' => $consentId,
                'whatsapp_account_id' => $account->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function syncBusinessProfile(WhatsAppAccount $account, WhatsAppDataSharingConsent $consent): void
    {
        if (! $consent->business_profile_shared || empty($account->waba_id) || empty($account->access_token)) {
            return;
        }

        $result = $this->embeddedSignupService->getPhoneNumberDetails($account->access_token, $account->waba_id);
        if (! $result['success']) {
            return;
        }

        $phoneNumber = collect($result['phone_numbers'] ?? [])
            ->firstWhere('id', $account->phone_number_id);

        if (! $phoneNumber) {
            return;
        }

        $account->fill([
            'display_phone_number' => $phoneNumber['display_phone_number'] ?? $account->display_phone_number,
            'name' => $phoneNumber['verified_name'] ?? $account->name,
            'quality_rating' => $phoneNumber['quality_rating'] ?? $account->quality_rating,
        ]);
        $account->save();
    }

    /**
     * @param  array<int, int|string>  $selectedContactIds
     */
    private function calculateContactCount(WhatsAppDataSharingConsent $consent, WhatsAppAccount $account, array $selectedContactIds): int
    {
        if (! $consent->contacts_shared) {
            return 0;
        }

        $query = $account->contacts()->withoutGlobalScopes();

        if ($consent->sharing_mode === WhatsAppDataSharingConsent::SHARING_MODE_SELECTED) {
            $query->whereIn('id', $selectedContactIds);
        }

        return $query->count();
    }

    /**
     * @param  array<int, int|string>  $selectedContactIds
     */
    private function calculateMessageCount(WhatsAppDataSharingConsent $consent, WhatsAppAccount $account, array $selectedContactIds): int
    {
        if (! $consent->conversation_history_shared) {
            return 0;
        }

        $query = WhatsAppMessage::withoutGlobalScopes()
            ->where('user_id', $consent->user_id)
            ->where('phone_number_id', $account->phone_number_id)
            ->where('created_at', '>=', now()->subMonths(6));

        if ($consent->sharing_mode === WhatsAppDataSharingConsent::SHARING_MODE_SELECTED) {
            $query->whereIn('contact_id', $selectedContactIds);
        }

        return $query->count();
    }

    private function markAccountStatus(WhatsAppAccount $account, string $status, ?string $error = null): void
    {
        $account->fill([
            'data_sync_status' => $status,
            'data_sync_error' => $error,
            'last_data_sync_at' => $status === 'completed' ? now() : $account->last_data_sync_at,
            'data_sync_settings' => [
                'history_months' => 6,
                'sharing_target' => 'internal_only',
            ],
        ]);

        $account->save();
    }
}
