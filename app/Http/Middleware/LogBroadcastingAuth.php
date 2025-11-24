<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogBroadcastingAuth
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('Broadcasting auth request received', [
            'method' => $request->method(),
            'url' => $request->url(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
            'token' => $request->bearerToken() ? 'Present' : 'Missing',
            'user' => auth('sanctum')->user() ? auth('sanctum')->user()->id : 'Not authenticated'
        ]);

        $response = $next($request);

        Log::info('Broadcasting auth response', [
            'status' => $response->status(),
            'content' => $response->getContent()
        ]);

        return $response;
    }
}
