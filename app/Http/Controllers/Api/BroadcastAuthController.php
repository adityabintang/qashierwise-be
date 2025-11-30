<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
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

        try {
            // Use Laravel's broadcast authorization
            $response = Broadcast::auth($request);
            
            Log::info('Broadcast auth response', [
                'response' => $response,
            ]);

            // If response is already a Response object, return it
            if ($response instanceof \Illuminate\Http\Response || $response instanceof \Symfony\Component\HttpFoundation\Response) {
                return $response;
            }

            // Otherwise, return as JSON
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('Broadcast auth exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => 'Authorization failed: ' . $e->getMessage()], 403);
        }
    }
}
