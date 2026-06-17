<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers (SEC-03). Kept conservative on purpose:
 * a full script-src CSP would break Livewire/Vite inline bootstrapping, so we
 * ship clickjacking + MIME-sniffing + referrer protections and a
 * frame-ancestors CSP, and leave HSTS to be emitted only over HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'       => '0', // modern browsers: disable legacy auditor, rely on CSP/escaping
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ];

        // Only advertise HSTS when actually served over TLS, so local HTTP dev
        // isn't pinned to https.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
