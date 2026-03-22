<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = array_keys(config('wolforix.supported_locales', []));
        $defaultLocale = config('wolforix.default_locale', config('app.fallback_locale', 'en'));
        $locale = $request->session()->get('locale')
            ?? $request->cookie('wolforix_locale')
            ?? config('app.locale');

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = $defaultLocale;
        }

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        return $next($request);
    }
}
