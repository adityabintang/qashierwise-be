<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a success JSON response
     *
     * @param  mixed  $data  The data to return
     * @param  string|null  $message  Translation key or message
     * @param  int  $code  HTTP status code
     */
    public static function success($data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = __($message);
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        // Include locale information in response
        $response['locale'] = app()->getLocale();

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response
     *
     * @param  string  $message  Translation key or error message
     * @param  int  $code  HTTP status code
     * @param  mixed  $errors  Additional error details
     */
    public static function error(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => __($message),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // Include locale information in response
        $response['locale'] = app()->getLocale();

        return response()->json($response, $code);
    }

    /**
     * Return a validation error response
     *
     * @param  mixed  $errors  Validation errors
     * @param  string|null  $message  Custom message or translation key
     */
    public static function validationError($errors, ?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'messages.error.validation',
            422,
            $errors
        );
    }

    /**
     * Return an unauthorized response
     *
     * @param  string|null  $message  Custom message or translation key
     */
    public static function unauthorized(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'messages.error.unauthorized',
            401
        );
    }

    /**
     * Return a forbidden response
     *
     * @param  string|null  $message  Custom message or translation key
     */
    public static function forbidden(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'messages.error.forbidden',
            403
        );
    }

    /**
     * Return a not found response
     *
     * @param  string|null  $message  Custom message or translation key
     */
    public static function notFound(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'messages.error.not_found',
            404
        );
    }

    /**
     * Return a server error response
     *
     * @param  string|null  $message  Custom message or translation key
     */
    public static function serverError(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'messages.error.server',
            500
        );
    }

    /**
     * Detect locale from request
     * Priority: locale parameter > Accept-Language header > session > default
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public static function detectLocale($request): string
    {
        // 1. Check for explicit locale parameter
        if ($request->has('locale')) {
            $locale = $request->input('locale');
            if (in_array($locale, config('app.supported_locales', ['en', 'id']))) {
                return $locale;
            }
        }

        // 2. Check Accept-Language header
        if ($request->hasHeader('Accept-Language')) {
            $acceptLanguage = $request->header('Accept-Language');
            $locale = self::parseAcceptLanguage($acceptLanguage);
            if ($locale) {
                return $locale;
            }
        }

        // 3. Check session
        if (session()->has('locale')) {
            return session('locale');
        }

        // 4. Use default
        return config('app.locale', 'en');
    }

    /**
     * Parse Accept-Language header
     */
    private static function parseAcceptLanguage(string $header): ?string
    {
        $locales = [];

        foreach (explode(',', $header) as $locale) {
            $parts = explode(';', $locale);
            $locales[] = trim($parts[0]);
        }

        $supportedLocales = config('app.supported_locales', ['en', 'id']);

        foreach ($locales as $locale) {
            $lang = explode('-', $locale)[0];
            if (in_array($lang, $supportedLocales)) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Set locale for API request
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public static function setLocaleFromRequest($request): void
    {
        $locale = self::detectLocale($request);
        app()->setLocale($locale);
    }
}
