<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request);

        // Validate locale is supported
        if (! in_array($locale, config('app.supported_locales'))) {
            $locale = config('app.locale');
        }

        // Set application locale
        app()->setLocale($locale);

        // Store locale in session for persistence
        session(['locale' => $locale]);

        return $next($request);
    }

    /**
     * Detect the user's preferred locale from multiple sources.
     * Priority: user preference → session → Accept-Language header → default
     */
    private function detectLocale(Request $request): string
    {
        // 1. Check session (highest priority - covers both guest and authenticated users)
        if (session()->has('locale')) {
            return session('locale');
        }

        // 2. Check user preference (if authenticated and no session yet)
        if (auth()->check() && auth()->user()->language_preference) {
            return auth()->user()->language_preference;
        }

        // 3. Check Accept-Language header
        if ($request->hasHeader('Accept-Language')) {
            $locale = $this->parseAcceptLanguage($request->header('Accept-Language'));
            if ($locale) {
                return $locale;
            }
        }

        // 4. Use default
        return config('app.locale');
    }

    /**
     * Parse Accept-Language header and return the first supported locale.
     */
    private function parseAcceptLanguage(string $header): ?string
    {
        $locales = [];

        // Parse Accept-Language header
        // Format: "en-US,en;q=0.9,id;q=0.8"
        foreach (explode(',', $header) as $locale) {
            $parts = explode(';', $locale);
            $locales[] = trim($parts[0]);
        }

        // Check each locale against supported locales
        foreach ($locales as $locale) {
            // Extract language code (e.g., "en" from "en-US")
            $lang = explode('-', $locale)[0];

            if (in_array($lang, config('app.supported_locales'))) {
                return $lang;
            }
        }

        return null;
    }
}
