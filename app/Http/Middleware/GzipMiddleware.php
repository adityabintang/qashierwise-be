<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GzipMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if ($content && function_exists('gzencode')) {
            $compressed = gzencode($content, 6);

            if ($compressed !== false) {
                $response->setContent($compressed);
                $response->headers->set('Content-Encoding', 'gzip');
                $response->headers->set('Content-Length', strlen($compressed));
                $response->headers->set('Vary', 'Accept-Encoding');
            }
        }

        return $response;
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (! str_contains($request->header('Accept-Encoding', ''), 'gzip')) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/') ||
               str_contains($contentType, 'application/json') ||
               str_contains($contentType, 'application/javascript');
    }
}
