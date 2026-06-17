{{-- Shared dismiss handler for any banner marked with .js-dismissible-banner + data-key.
     Hides the banner for the rest of the current login (server session); it reappears
     on the next login when the session is regenerated. --}}
@once
@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-dismissible-banner .btn-close');
        if (! btn) return;
        const banner = btn.closest('.js-dismissible-banner');
        const key = banner.dataset.key;
        banner.remove();
        fetch('{{ route('admin.plan-banner.dismiss') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ resource: key }),
        });
    });
</script>
@endpush
@endonce
