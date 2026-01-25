<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = $this->getContentSecurityPolicy();
        $response->headers->set('Content-Security-Policy', $csp);

        $response->headers->set('X-Frame-Options', 'DENY');

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        $response->headers->set('X-XSS-Protection', '1; mode=block');

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set('Permissions-Policy', implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
        ]));

        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function getContentSecurityPolicy(): string
    {
        $directives = [
            "default-src 'self'",

            "script-src 'self' 'unsafe-inline' 'unsafe-eval' ".implode(' ', [
                'https://cdn.tailwindcss.com',
                'https://cdnjs.cloudflare.com',
                'https://cdn.jsdelivr.net',
                'https://unpkg.com',
                'https://www.googletagmanager.com',
                'https://www.google-analytics.com',
                'https://js.pusher.com',
                'https://app.midtrans.com',
                'https://app.sandbox.midtrans.com',
                'https://connect.facebook.net',
            ]),

            "style-src 'self' 'unsafe-inline' ".implode(' ', [
                'https://fonts.googleapis.com',
                'https://cdn.tailwindcss.com',
                'https://cdnjs.cloudflare.com',
            ]),

            "font-src 'self' ".implode(' ', [
                'https://fonts.gstatic.com',
                'https://cdnjs.cloudflare.com',
            ]),

            "img-src 'self' data: https: ".implode(' ', [
                'https://www.google-analytics.com',
                'https://api.dicebear.com',
                'https://images.unsplash.com',
                'https://api.qrserver.com',
            ]),

            "connect-src 'self' ".implode(' ', [
                'https://www.google-analytics.com',
                'https://region1.google-analytics.com',
                // Pusher WebSocket connections (all regions)
                'wss://*.pusher.com',
                'https://*.pusher.com',
                'wss://ws-ap1.pusher.com',
                'wss://ws-ap2.pusher.com',
                'wss://ws-ap3.pusher.com',
                'wss://ws-ap4.pusher.com',
                'wss://ws-eu.pusher.com',
                'wss://ws-us2.pusher.com',
                'wss://ws-us3.pusher.com',
                'wss://ws-mt1.pusher.com',
                'https://sockjs-ap1.pusher.com',
                'https://sockjs-ap2.pusher.com',
                'https://sockjs-ap3.pusher.com',
                'https://sockjs-ap4.pusher.com',
                'https://sockjs-eu.pusher.com',
                'https://sockjs-us2.pusher.com',
                'https://sockjs-us3.pusher.com',
                'https://sockjs-mt1.pusher.com',
                // Other services
                'https://nominatim.openstreetmap.org',
                'https://app.midtrans.com',
                'https://app.sandbox.midtrans.com',
                'https://api.midtrans.com',
                'https://api.sandbox.midtrans.com',
                'https://api.xendit.co',
            ]),

            "frame-src 'self' ".implode(' ', [
                'https://www.google.com',
                'https://maps.google.com',
                'https://www.youtube.com',
                'https://www.youtube-nocookie.com',
                'https://app.midtrans.com',
                'https://app.sandbox.midtrans.com',
                'https://connect.facebook.net',
            ]),

            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ];

        return implode('; ', $directives);
    }
}
