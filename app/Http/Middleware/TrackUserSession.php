<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            try {
                $sessionId = $request->session()->getId();

                UserSession::updateOrCreate(
                    ['session_id' => $sessionId],
                    [
                        'user_id'        => $user->id,
                        'device_label'   => $this->parseUserAgent((string) $request->userAgent()),
                        'ip'             => $request->ip(),
                        'user_agent'     => substr((string) $request->userAgent(), 0, 512),
                        'last_active_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                // Table may not exist yet (pending migration) — never block the request.
                \Illuminate\Support\Facades\Log::debug('TrackUserSession skipped: ' . $e->getMessage());
            }
        }

        return $next($request);
    }

    private function parseUserAgent(string $ua): string
    {
        $ua = strtolower($ua);
        return match (true) {
            str_contains($ua, 'iphone')               => 'iPhone',
            str_contains($ua, 'ipad')                 => 'iPad',
            str_contains($ua, 'android')              => 'Android device',
            str_contains($ua, 'windows')              => 'Windows PC',
            str_contains($ua, 'mac os'),
            str_contains($ua, 'macintosh')            => 'Mac',
            str_contains($ua, 'linux')                => 'Linux PC',
            default                                   => 'Unknown device',
        };
    }
}
