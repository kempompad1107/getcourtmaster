<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Offline</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4"
      style="background:radial-gradient(1100px 520px at 12% -10%,rgba(16,185,129,.22),transparent 60%),radial-gradient(900px 500px at 100% 110%,rgba(59,130,246,.16),transparent 55%),linear-gradient(160deg,#3a4868 0%,#172643 55%,#0f1d36 100%);">
    <div class="max-w-md w-full text-center">
        <div class="bg-white rounded-2xl border border-white/60 p-10" style="box-shadow:0 30px 60px -20px rgba(0,0,0,.6);">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 12h.01M3 3l18 18" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900">You're offline</h1>
            <p class="mt-2 text-sm text-gray-500">
                CourtMaster needs an internet connection to load. Check your network and try again.
            </p>

            <div class="mt-6">
                <button onclick="window.location.reload()"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Try Again
                </button>
            </div>

            <p class="mt-6 text-xs text-gray-400">
                Bookings created while offline will sync automatically once you reconnect.
            </p>
        </div>
    </div>

    <script>
        window.addEventListener('online', () => window.location.reload());
    </script>
</body>
</html>
