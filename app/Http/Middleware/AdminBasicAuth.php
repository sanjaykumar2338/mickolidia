<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminBasicAuth
{
    public const SESSION_KEY = 'admin.authenticated';

    public const USERNAME_KEY = 'admin.username';

    public static function isAuthenticated(Request $request): bool
    {
        $username = (string) config('wolforix.admin_auth.username', 'admin');

        return $request->session()->get(self::SESSION_KEY) === true
            && $request->session()->get(self::USERNAME_KEY) === $username;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::isAuthenticated($request)) {
            return $next($request);
        }

        $request->session()->put('admin.intended', $request->fullUrl());

        return redirect()->route('admin.login');
    }
}
