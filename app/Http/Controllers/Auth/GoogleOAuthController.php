<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleOAuthController extends Controller
{
    public function __construct(
        protected GoogleCalendarService $calendarService
    ) {}

    /**
     * Redirect user to Google OAuth consent screen.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        try {
            $authUrl = $this->calendarService->getAuthorizationUrl();

            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('Failed to generate Google OAuth URL', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menghubungkan ke Google Calendar. Silakan coba lagi.');
        }
    }

    /**
     * Handle Google OAuth callback and store tokens.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return $this->redirectToDashboard()
                    ->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
            }

            if ($request->has('error')) {
                Log::warning('Google OAuth authorization denied', [
                    'error' => $request->get('error'),
                    'user_id' => $user->id,
                ]);

                return $this->redirectToDashboard()
                    ->with('error', 'Otorisasi Google Calendar dibatalkan.');
            }

            if (! $request->has('code')) {
                return $this->redirectToDashboard()
                    ->with('error', 'Kode otorisasi tidak ditemukan.');
            }

            // Exchange authorization code for tokens
            $token = $this->calendarService->authenticate($request->get('code'));

            // Store access token and refresh token
            $user->update([
                'google_calendar_refresh_token' => json_encode($token),
                'google_calendar_email' => $this->getEmailFromToken($token),
            ]);

            Log::info('Google Calendar connected successfully', [
                'user_id' => $user->id,
                'email' => $user->google_calendar_email,
            ]);

            return $this->redirectToDashboard()
                ->with('success', 'Google Calendar berhasil terhubung! Reservasi baru akan otomatis masuk ke kalender Anda.');

        } catch (Exception $e) {
            Log::error('Failed to handle Google OAuth callback', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->redirectToDashboard()
                ->with('error', 'Gagal menghubungkan Google Calendar: '.$e->getMessage());
        }
    }

    /**
     * Disconnect Google Calendar by removing stored tokens.
     */
    public function disconnectCalendar(): RedirectResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()
                    ->json(['error' => 'Unauthorized'], 401)
                    ->throwResponse();
            }

            // Revoke access token if possible
            try {
                if ($user->google_calendar_refresh_token) {
                    $this->calendarService->setAccessToken($user->google_calendar_refresh_token);

                    // You can add token revocation here if needed
                    // $this->calendarService->revokeToken();
                }
            } catch (Exception $e) {
                // Continue even if revocation fails
                Log::warning('Failed to revoke Google token', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
            }

            // Clear stored tokens
            $user->update([
                'google_calendar_refresh_token' => null,
                'google_calendar_email' => null,
            ]);

            Log::info('Google Calendar disconnected', [
                'user_id' => $user->id,
            ]);

            return redirect()->back()
                ->with('success', 'Google Calendar berhasil diputuskan.');

        } catch (Exception $e) {
            Log::error('Failed to disconnect Google Calendar', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memutuskan Google Calendar: '.$e->getMessage());
        }
    }

    /**
     * Redirect to dashboard or fallback URL.
     */
    protected function redirectToDashboard(): RedirectResponse
    {
        // Check if dashboard route exists
        if (\Route::has('dashboard')) {
            return redirect()->route('dashboard');
        }

        // Fallback to root
        return redirect('/');
    }

    /**
     * Extract email from token data.
     */
    protected function getEmailFromToken(array $token): ?string
    {
        try {
            // Set token to get calendar info
            $this->calendarService->setAccessToken(json_encode($token));

            return $this->calendarService->getCalendarEmail();
        } catch (Exception $e) {
            Log::warning('Failed to get email from calendar', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to authenticated user's email
            return Auth::user()->email ?? null;
        }
    }
}
