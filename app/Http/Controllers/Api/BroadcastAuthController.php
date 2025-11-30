<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BroadcastAuthController extends Controller
{
    /**
     * Authenticate the request for channel access.
     */
    public function authenticate(Request $request)
    {
        $user = $request->user();
        
        Log::info('Custom broadcast auth called', [
            'user_id' => $user ? $user->id : null,
            'channel_name' => $request->input('channel_name'),
            'socket_id' => $request->input('socket_id'),
        ]);

        if (!$user) {
            Log::warning('Broadcast auth failed: No authenticated user');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        if (!$channelName || !$socketId) {
            Log::warning('Broadcast auth failed: Missing channel_name or socket_id');
            return response()->json(['error' => 'Missing channel_name or socket_id'], 400);
        }

        // Check if user is authorized for this channel
        // Channel format: private-whatsapp.{userId}
        if (preg_match('/^private-whatsapp\.(\d+)$/', $channelName, $matches)) {
            $requestedUserId = (int) $matches[1];
            
            if ($user->id !== $requestedUserId) {
                Log::warning('Broadcast auth failed: User not authorized for channel', [
                    'user_id' => $user->id,
                    'requested_user_id' => $requestedUserId,
                ]);
                return response()->json(['error' => 'Forbidden'], 403);
            }
        } else {
            Log::warning('Broadcast auth failed: Invalid channel format', [
                'channel_name' => $channelName,
            ]);
            return response()->json(['error' => 'Invalid channel'], 400);
        }

        try {
            $key = config('broadcasting.connections.pusher.key');
            $secret = config('broadcasting.connections.pusher.secret');
            $appId = config('broadcasting.connections.pusher.app_id');
            
            Log::info('Pusher config', [
                'key' => $key,
                'secret' => $secret ? 'SET' : 'MISSING',
                'app_id' => $appId,
            ]);

            // Generate auth signature manually (most reliable method)
            $stringToSign = $socketId . ':' . $channelName;
            $signature = hash_hmac('sha256', $stringToSign, $secret);
            
            $auth = [
                'auth' => $key . ':' . $signature
            ];
            
            Log::info('Broadcast auth success', [
                'user_id' => $user->id,
                'channel' => $channelName,
                'socket_id' => $socketId,
                'auth' => $auth,
            ]);

            return response()->json($auth);
            
        } catch (\Exception $e) {
            Log::error('Broadcast auth exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => 'Authorization failed: ' . $e->getMessage()], 403);
        }
    }
}
