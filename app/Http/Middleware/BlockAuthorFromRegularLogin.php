<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockAuthorFromRegularLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->isAuthor()) {
            return redirect('/admin')->with('error', 'Authors must use /admin panel');
        }

        return $next($request);
    }
}
