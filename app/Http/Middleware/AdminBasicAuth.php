<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('wolforix.admin_auth.username', 'admin');
        $password = (string) config('wolforix.admin_auth.password', 'wolforix-admin');
        $realm = (string) config('wolforix.admin_auth.realm', 'Wolforix Admin');

        if ($request->getUser() === $username && $request->getPassword() === $password) {
            return $next($request);
        }

        return response('Authentication required.', 401, [
            'WWW-Authenticate' => sprintf('Basic realm="%s"', addslashes($realm)),
        ]);
    }
}
