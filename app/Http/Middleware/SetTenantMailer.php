<?php

namespace App\Http\Middleware;

use App\Services\TenantMailManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For an authenticated tenant user, make that tenant's business mailer the
 * request default (own SMTP / .env fallback / discard). Guests and tenant-less
 * users (e.g. super-admin, pre-auth OTP) are left on the platform .env mailer,
 * so login/OTP/password-reset are never affected.
 */
class SetTenantMailer
{
    public function __construct(private readonly TenantMailManager $mail) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user && $user->tenant) {
            $this->mail->apply($user->tenant);
        }

        return $next($request);
    }
}
