<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatsAppDataSharingConsentRequest;
use App\Http\Requests\UpdateWhatsAppDataSharingConsentRequest;
use App\Jobs\SyncWhatsAppDataJob;
use App\Models\WhatsAppDataSharingConsent;
use App\Services\WhatsAppDataSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppDataSharingController extends Controller
{
    protected WhatsAppDataSharingService $whatsAppDataSharingService;

    public function __construct(WhatsAppDataSharingService $whatsAppDataSharingService)
    {
        $this->whatsAppDataSharingService = $whatsAppDataSharingService;
    }

    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'disclosure' => $this->whatsAppDataSharingService->getDisclosureInfo(),
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $userId = $request->user()->getEffectiveUserId();
        $account = $this->whatsAppDataSharingService->getActiveAccountForUser($userId);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found.',
            ], 404);
        }

        $consent = $this->whatsAppDataSharingService->getConsentForUserAndAccount($userId, $account->id);

        return response()->json([
            'success' => true,
            'data' => [
                'account_connected' => true,
                'consent' => $consent,
                'sync' => [
                    'status' => $account->data_sync_status ?? 'idle',
                    'last_synced_at' => $account->last_data_sync_at,
                    'error' => $account->data_sync_error,
                ],
            ],
        ]);
    }

    public function contactsPreview(Request $request): JsonResponse
    {
        $userId = $request->user()->getEffectiveUserId();
        $account = $this->whatsAppDataSharingService->getActiveAccountForUser($userId);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found.',
            ], 404);
        }

        $consent = $this->whatsAppDataSharingService->getConsentForUserAndAccount($userId, $account->id);
        $selectedContactIds = $consent?->selected_contact_ids ?? [];

        $contacts = $this->whatsAppDataSharingService
            ->getContactsPreview($userId, $account->phone_number_id)
            ->map(function ($contact) use ($selectedContactIds) {
                return [
                    'id' => $contact->id,
                    'wa_id' => $contact->wa_id,
                    'name' => $contact->name,
                    'profile_pic_url' => $contact->profile_pic_url,
                    'last_message_at' => $contact->last_message_at,
                    'message_count_6_months' => (int) ($contact->message_count_6_months ?? 0),
                    'selected' => in_array($contact->id, $selectedContactIds, true),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $contacts,
            'total' => $contacts->count(),
        ]);
    }

    public function store(StoreWhatsAppDataSharingConsentRequest $request): JsonResponse
    {
        $userId = $request->user()->getEffectiveUserId();
        $account = $this->whatsAppDataSharingService->getActiveAccountForUser($userId);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found.',
            ], 404);
        }

        $consent = $this->whatsAppDataSharingService->upsertConsent(
            $account,
            $userId,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        SyncWhatsAppDataJob::dispatch($consent->id);

        return response()->json([
            'success' => true,
            'message' => 'Data sharing preferences saved. Sync has been queued.',
            'data' => [
                'consent_id' => $consent->id,
                'status' => $consent->status,
                'conversation_months' => 6,
                'sharing_mode' => $consent->sharing_mode,
                'sync_will_start' => true,
            ],
        ], 201);
    }

    public function update(UpdateWhatsAppDataSharingConsentRequest $request): JsonResponse
    {
        $userId = $request->user()->getEffectiveUserId();
        $account = $this->whatsAppDataSharingService->getActiveAccountForUser($userId);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found.',
            ], 404);
        }

        $consent = $this->whatsAppDataSharingService->getConsentForUserAndAccount($userId, $account->id);
        if (! $consent) {
            return response()->json([
                'success' => false,
                'message' => 'No consent found. Create consent first.',
            ], 404);
        }

        $updatedConsent = $this->whatsAppDataSharingService->updateConsent(
            $consent,
            $userId,
            $account->phone_number_id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        SyncWhatsAppDataJob::dispatch($updatedConsent->id);

        return response()->json([
            'success' => true,
            'message' => 'Data sharing preferences updated. Sync has been queued.',
            'data' => [
                'consent_id' => $updatedConsent->id,
                'status' => $updatedConsent->status,
                'conversation_months' => 6,
                'sharing_mode' => $updatedConsent->sharing_mode,
                'sync_will_start' => true,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $userId = $request->user()->getEffectiveUserId();
        $account = $this->whatsAppDataSharingService->getActiveAccountForUser($userId);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found.',
            ], 404);
        }

        $consent = $this->whatsAppDataSharingService->getConsentForUserAndAccount($userId, $account->id);
        if (! $consent) {
            return response()->json([
                'success' => false,
                'message' => 'No consent found to revoke.',
            ], 404);
        }

        $revoked = $this->whatsAppDataSharingService->revokeConsent($consent);

        return response()->json([
            'success' => true,
            'message' => 'Data sharing consent revoked.',
            'data' => [
                'consent_id' => $revoked->id,
                'status' => $revoked->status,
            ],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()->getEffectiveUserId();
        $account = $this->whatsAppDataSharingService->getActiveAccountForUser($userId);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'No connected WhatsApp account found.',
            ], 404);
        }

        $consent = $this->whatsAppDataSharingService->getConsentForUserAndAccount($userId, $account->id);
        if (! $consent || $consent->status !== WhatsAppDataSharingConsent::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Active data sharing consent is required before sync.',
            ], 422);
        }

        SyncWhatsAppDataJob::dispatch($consent->id);

        return response()->json([
            'success' => true,
            'message' => 'Data sync queued successfully.',
            'data' => [
                'consent_id' => $consent->id,
            ],
        ]);
    }
}
