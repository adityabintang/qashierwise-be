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
                    
                    // Verify token with API
                    fetch(window.location.origin + '/api/me', {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            // Token invalid or expired
                            localStorage.removeItem('token');
                            localStorage.removeItem('user');
                            window.location.href = '/login';
                        }
                    })
                    .catch(() => {
                        // Network error or token invalid
                        localStorage.removeItem('token');
                        localStorage.removeItem('user');
                        window.location.href = '/login';
                    });
                })();
            </script>
            ";
            
            $content = $response->getContent();
            $content = str_replace('</head>', $authCheckScript . '</head>', $content);
            $response->setContent($content);
        }
        
        return $response;
    }
}
