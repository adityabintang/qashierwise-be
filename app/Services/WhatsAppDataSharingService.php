<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppDataSharingConsent;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Collection;

class WhatsAppDataSharingService
{
    public function getDisclosureInfo(): array
    {
        return [
            'title' => 'Share Your WhatsApp Business Data',
            'description' => 'You can choose what data to share from your connected WhatsApp Business account.',
            'items' => [
                [
                    'id' => 'business_profile',
                    'title' => 'Business Profile Information',
                    'description' => 'Business name, description, and contact profile configured on WhatsApp Business.',
                    'required' => false,
                ],
                [
                    'id' => 'contacts',
                    'title' => 'Contact List',
                    'description' => 'Contacts who have interacted with your WhatsApp Business account.',
                    'required' => false,
                ],
                [
                    'id' => 'conversation_history',
                    'title' => 'Conversation History',
                    'description' => 'Messages from the last 6 months. You can share all chats or selected chats only.',
                    'required' => false,
                ],
            ],
            'constraints' => [
                'history_months' => 6,
                'sharing_target' => 'internal_only',
            ],
            'notes' => [
                'You can update or revoke data sharing preferences anytime.',
                'Conversation sharing supports all chats or selected chats.',
                'Imported data is used only within this platform.',
            ],
        ];
    }

    public function getActiveAccountForUser(int $userId): ?WhatsAppAccount
    {
        return WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    public function getConsentForUserAndAccount(int $userId, int $accountId): ?WhatsAppDataSharingConsent
    {
        return WhatsAppDataSharingConsent::query()
            ->where('user_id', $userId)
            ->where('whatsapp_account_id', $accountId)
            ->first();
    }

    public function upsertConsent(WhatsAppAccount $account, int $userId, array $payload, ?string $ipAddress, ?string $userAgent): WhatsAppDataSharingConsent
    {
        $sharingMode = $payload['sharing_mode'] ?? WhatsAppDataSharingConsent::SHARING_MODE_ALL;
        $selectedContactIds = $this->sanitizeSelectedContactIds($userId, $account->phone_number_id, $payload['selected_contact_ids'] ?? []);

        if ($sharingMode === WhatsAppDataSharingConsent::SHARING_MODE_ALL) {
            $selectedContactIds = [];
        }

        $now = now();

        return WhatsAppDataSharingConsent::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'whatsapp_account_id' => $account->id,
            ],
            [
                'business_profile_shared' => (bool) ($payload['business_profile_shared'] ?? true),
                'contacts_shared' => (bool) ($payload['contacts_shared'] ?? true),
                'conversation_history_shared' => (bool) ($payload['conversation_history_shared'] ?? true),
                'conversation_months' => 6,
                'sharing_mode' => $sharingMode,
                'selected_contact_ids' => $selectedContactIds,
                'status' => WhatsAppDataSharingConsent::STATUS_ACTIVE,
                'consent_given_at' => $now,
                'consent_updated_at' => $now,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]
        );
    }

    public function updateConsent(WhatsAppDataSharingConsent $consent, int $userId, string $phoneNumberId, array $payload, ?string $ipAddress, ?string $userAgent): WhatsAppDataSharingConsent
    {
        $sharingMode = $payload['sharing_mode'] ?? $consent->sharing_mode;
        $selectedContactIds = $payload['selected_contact_ids'] ?? $consent->selected_contact_ids ?? [];
        $selectedContactIds = $this->sanitizeSelectedContactIds($userId, $phoneNumberId, $selectedContactIds);

        if ($sharingMode === WhatsAppDataSharingConsent::SHARING_MODE_ALL) {
            $selectedContactIds = [];
        }

        $consent->fill([
            'business_profile_shared' => $payload['business_profile_shared'] ?? $consent->business_profile_shared,
            'contacts_shared' => $payload['contacts_shared'] ?? $consent->contacts_shared,
            'conversation_history_shared' => $payload['conversation_history_shared'] ?? $consent->conversation_history_shared,
            'conversation_months' => 6,
            'sharing_mode' => $sharingMode,
            'selected_contact_ids' => $selectedContactIds,
            'status' => WhatsAppDataSharingConsent::STATUS_ACTIVE,
            'consent_updated_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        $consent->save();

        return $consent;
    }

    public function revokeConsent(WhatsAppDataSharingConsent $consent): WhatsAppDataSharingConsent
    {
        $consent->fill([
            'status' => WhatsAppDataSharingConsent::STATUS_REVOKED,
            'conversation_history_shared' => false,
            'synced_contact_count' => 0,
            'synced_message_count' => 0,
            'consent_updated_at' => now(),
        ]);

        $consent->save();

        return $consent;
    }

    public function getContactsPreview(int $userId, string $phoneNumberId): Collection
    {
        $cutoffDate = now()->subMonths(6);

        return WhatsAppContact::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('phone_number_id', $phoneNumberId)
            ->withCount([
                'messages as message_count_6_months' => function ($query) use ($cutoffDate) {
                    $query->withoutGlobalScopes()->where('created_at', '>=', $cutoffDate);
                },
            ])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function getShareableMessageCount(int $userId, string $phoneNumberId, ?array $selectedContactIds, bool $conversationHistoryShared): int
    {
        if (! $conversationHistoryShared) {
            return 0;
        }

        $query = WhatsAppMessage::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('phone_number_id', $phoneNumberId)
            ->where('created_at', '>=', now()->subMonths(6));

        if (! empty($selectedContactIds)) {
            $query->whereIn('contact_id', $selectedContactIds);
        }

        return $query->count();
    }

    /**
     * @param  array<int, int|string>  $selectedContactIds
     * @return array<int, int>
     */
    private function sanitizeSelectedContactIds(int $userId, string $phoneNumberId, array $selectedContactIds): array
    {
        if (empty($selectedContactIds)) {
            return [];
        }

        return WhatsAppContact::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('phone_number_id', $phoneNumberId)
            ->whereIn('id', $selectedContactIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
