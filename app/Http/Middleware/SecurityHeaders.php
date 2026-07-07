<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add baseline security headers to every response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', config('security.headers.frame_options', 'SAMEORIGIN'));
        $response->headers->set('Referrer-Policy', config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set(
            'Permissions-Policy',
            config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()')
        );

        if (config('security.headers.hsts.enabled', false) && $request->isSecure()) {
            $maxAge = (int) config('security.headers.hsts.max_age', 31536000);
            $includeSubdomains = config('security.headers.hsts.include_subdomains', true) ? '; includeSubDomains' : '';
            $preload = config('security.headers.hsts.preload', false) ? '; preload' : '';
            $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}{$includeSubdomains}{$preload}");
        }

        return $response;
    }
}
