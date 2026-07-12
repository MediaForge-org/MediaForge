<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RedirectIfAuthenticated
{
    /**
     * @param  array<int, string>  $guards
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        foreach ($guards === [] ? [null] : $guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return new RedirectResponse('/dashboard');
            }
        }

        return $next($request);
    }
}
