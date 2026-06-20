<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Cancelled</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4"
      style="background:radial-gradient(1100px 520px at 12% -10%,rgba(16,185,129,.22),transparent 60%),radial-gradient(900px 500px at 100% 110%,rgba(59,130,246,.16),transparent 55%),linear-gradient(160deg,#3a4868 0%,#172643 55%,#0f1d36 100%);">
    <div class="max-w-md w-full text-center">
        <div class="bg-white rounded-2xl border border-white/60 p-10" style="box-shadow:0 30px 60px -20px rgba(0,0,0,.6);">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900">Account Cancelled</h1>
            <p class="mt-2 text-sm text-gray-500">
                This venue's CourtMaster account has been cancelled and is no longer active.
            </p>

            <div class="mt-6 space-y-3">
                @auth
                    @if(auth()->user()->isBusinessOwner())
                        <p class="text-sm text-gray-600">
                            To reactivate your account, please contact support.
                        </p>
                        <a href="mailto:support@courtmaster.app"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                            Contact Support
                        </a>
                    @else
                        <p class="text-sm text-gray-600">
                            Please contact the venue directly for more information.
                        </p>
                    @endif
                @endauth
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
