<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // 1. User preference (database)
        if ($request->user() && $request->user()->preferred_locale) {
            $locale = $request->user()->preferred_locale;
        }

        // 2. Session
        if (!$locale && Session::has('locale')) {
            $locale = Session::get('locale');
        }

        // 3. Browser language
        if (!$locale && $request->server('HTTP_ACCEPT_LANGUAGE')) {
            $browserLocale = substr($request->server('HTTP_ACCEPT_LANGUAGE'), 0, 2);
            if (in_array($browserLocale, ['en', 'fr', 'ht'])) {
                $locale = $browserLocale;
            }
        }

        // 4. Default
        if (!$locale) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);
        Session::put('locale', $locale);

        // Add Content-Language header
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
        //return $next($request);
    }
}