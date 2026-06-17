<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4"
      style="background:radial-gradient(1100px 520px at 12% -10%,rgba(16,185,129,.22),transparent 60%),radial-gradient(900px 500px at 100% 110%,rgba(59,130,246,.16),transparent 55%),linear-gradient(160deg,#3a4868 0%,#172643 55%,#0f1d36 100%);">
    <div class="max-w-md w-full text-center">
        <div class="bg-white rounded-2xl border border-white/60 p-10" style="box-shadow:0 30px 60px -20px rgba(0,0,0,.6);">
            <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900">Account Suspended</h1>
            <p class="mt-2 text-sm text-gray-500">
                Your account has been suspended. This may be due to a payment issue or a violation of our terms of service.
            </p>

            <div class="mt-6 space-y-3">
                <p class="text-sm text-gray-600">
                    If this is due to an unpaid subscription, you can settle it now to restore access.
                </p>
                @auth
                    @if(auth()->user()->isBusinessOwner())
                        <a href="{{ route('admin.subscription') }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                            Renew / manage subscription
                        </a>
                    @endif
                @endauth
                <p class="text-sm text-gray-600">
                    Otherwise, please contact support to resolve this issue.
                </p>
                <a href="mailto:support@courtmaster.app"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Contact Support
                </a>
            </div>

            <div class="mt-8 border-t border-gray-100 pt-5">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
