<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\SendReservationReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Internal controller to handle Qstash callbacks for reservation reminders.
 * This endpoint is called by Qstash when a scheduled reminder is due.
 */
class ReservationReminderController extends Controller
{
    /**
     * Handle the Qstash callback for sending reservation reminders.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Received reservation reminder callback from Qstash', [
            'payload' => $payload,
        ]);

        // Qstash may send data in different formats:
        // 1. {"body": "{\"reservation_id\":6}"} - nested with JSON string
        // 2. {"reservation_id": 6} - direct in payload
        // 3. {"payload": []} - empty payload

        $reservationId = null;
        $body = null;

        // First, check if payload is inside a 'payload' key (Qstash wraps it)
        if (isset($payload['payload']) && is_array($payload['payload'])) {
            $innerPayload = $payload['payload'];

            // Check for 'body' key which contains JSON string
            if (isset($innerPayload['body']) && is_string($innerPayload['body'])) {
                $body = json_decode($innerPayload['body'], true);
            }

            // Try to get reservation_id from inner payload or decoded body
            $reservationId = $innerPayload['reservation_id']
                ?? ($body['reservation_id'] ?? null);
        } elseif (isset($payload['body'])) {
            // Direct body key
            if (is_string($payload['body'])) {
                $body = json_decode($payload['body'], true);
            }
            $reservationId = $body['reservation_id'] ?? $payload['reservation_id'] ?? null;
        } else {
            // Direct in payload
            $reservationId = $payload['reservation_id'] ?? null;
        }

        if (! $reservationId) {
            Log::error('No reservation_id in Qstash payload', [
                'payload' => $payload,
                'body' => $body,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No reservation_id provided',
            ], 400);
        }

        Log::info('Processing reservation reminder', [
            'reservation_id' => $reservationId,
        ]);

        // Dispatch the job to send the reminder
        SendReservationReminder::dispatch((int) $reservationId);

        return response()->json([
            'success' => true,
            'message' => 'Reminder job dispatched',
            'reservation_id' => $reservationId,
        ]);
    }
}
