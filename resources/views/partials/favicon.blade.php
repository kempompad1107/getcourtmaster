{{-- Platform favicon (set by super admin in Platform Settings). Falls back to
     the bundled PWA icon when none has been uploaded. --}}
@php $platformFavicon = \App\Models\PlatformSetting::branding()['favicon'] ?? null; @endphp
@if($platformFavicon)
    <link rel="icon" href="{{ file_url($platformFavicon) }}">
@else
    <link rel="icon" href="{{ asset('icons/icon-192x192.png') }}">
@endif
