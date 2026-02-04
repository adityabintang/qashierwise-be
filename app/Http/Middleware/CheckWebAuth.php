<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWebAuth
{
    /**
     * Handle an incoming request.
     * Check if user has valid token in localStorage (client-side check via view)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For web routes, we'll inject JavaScript to check localStorage token
        // If no token or invalid, redirect to login
        $response = $next($request);

        // Only inject script for HTML responses
        if ($response->headers->get('Content-Type') &&
            str_contains($response->headers->get('Content-Type'), 'text/html')) {

            $authCheckScript = "
            <script>
                (function() {
                    const token = localStorage.getItem('token');
                    if (!token) {
                        window.location.href = '/login';
                        return;
                    }

                    // Add retry logic and delay to prevent race conditions during login
                    let retryCount = 0;
                    const maxRetries = 3;
                    const retryDelay = 500; // 500ms delay between retries

                    function verifyToken() {
                        fetch(window.location.origin + '/api/me', {
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                if (response.status === 401 && retryCount < maxRetries) {
                                    // Retry on 401 - token might still be initializing
                                    retryCount++;
                                    console.log('[CheckWebAuth] Token verification failed, retrying... (' + retryCount + '/' + maxRetries + ')');
                                    setTimeout(verifyToken, retryDelay);
                                    return;
                                }
                                // Token invalid or expired after retries
                                localStorage.removeItem('token');
                                localStorage.removeItem('user');
                                window.location.href = '/login';
                            }
                        })
                        .catch(() => {
                            if (retryCount < maxRetries) {
                                // Retry on network error
                                retryCount++;
                                console.log('[CheckWebAuth] Network error, retrying... (' + retryCount + '/' + maxRetries + ')');
                                setTimeout(verifyToken, retryDelay);
                                return;
                            }
                            // Network error or token invalid after retries
                            localStorage.removeItem('token');
                            localStorage.removeItem('user');
                            window.location.href = '/login';
                        });
                    }

                    // Delay initial check to allow token to be fully set after login
                    setTimeout(verifyToken, 300);
                })();
            </script>
            ";

            $content = $response->getContent();
            $content = str_replace('</head>', $authCheckScript.'</head>', $content);
            $response->setContent($content);
        }

        return $response;
    }
}
