<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set PostgreSQL session context for Row Level Security (RLS).
 *
 * This middleware sets the app.current_user_id session variable in PostgreSQL
 * for the duration of the request, enabling Row Level Security policies to
 * automatically filter data based on the authenticated user.
 *
 * The session variable is reset after the request completes to prevent
 * context leakage between requests.
 *
 * Usage: Apply to authenticated routes that access RLS-protected tables.
 *
 * @see Requirements 3.1, 3.2, 3.3
 */
class SetPostgresUserContext
{
    /**
     * Handle an incoming request.
     *
     * Sets the PostgreSQL session variable app.current_user_id to the
     * authenticated user's ID, enabling RLS policies to filter data.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only set context if user is authenticated and using PostgreSQL
        if ($user = $request->user()) {
            // Check if we're using PostgreSQL
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                try {
                    // Set PostgreSQL session variable for RLS
                    // This enables RLS policies to filter data by current user
                    // Note: SET LOCAL doesn't support prepared statements, so we use
                    // intval() to ensure the user ID is a safe integer value
                    $userId = intval($user->id);
                    DB::unprepared("SET LOCAL app.current_user_id = {$userId}");
                } catch (\Exception $e) {
                    // Log but don't fail - RLS is an additional security layer
                    // Application-level filtering is still in place
                    \Log::warning('Failed to set PostgreSQL user context', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id,
                    ]);
                }
            }
            // For other databases (MySQL, SQLite), RLS is not supported
            // Application-level filtering should be used instead
        }

        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * Resets the PostgreSQL session context after the request completes
     * to prevent context leakage between requests.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Reset the session variable after request completes
        // This prevents context leakage between requests
        if ($request->user()) {
            // Check if we're using PostgreSQL
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                try {
                    DB::unprepared('RESET app.current_user_id');
                } catch (\Exception $e) {
                    // Log but don't fail if reset fails
                    // The connection will be returned to pool and reset anyway
                    \Log::warning('Failed to reset PostgreSQL user context', [
                        'error' => $e->getMessage(),
                        'user_id' => $request->user()->id,
                    ]);
                }
            }
        }
    }
}
