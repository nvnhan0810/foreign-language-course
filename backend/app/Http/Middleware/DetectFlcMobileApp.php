<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DetectFlcMobileApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $isApp = $request->header('X-FLC-App') === '1'
            || str_contains($request->userAgent() ?? '', 'FLCApp/')
            || $request->cookie('flc_app') === '1'
            || $request->query('flc_app') === '1';

        View::share('isFlcApp', $isApp);
        $request->attributes->set('flc_app', $isApp);

        $response = $next($request);

        if ($isApp && $request->cookie('flc_app') !== '1') {
            return $response->withCookie(
                cookie('flc_app', '1', 525600, '/', null, $request->isSecure(), true, false, 'Lax')
            );
        }

        return $response;
    }
}
