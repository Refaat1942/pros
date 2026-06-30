@once
    @push('styles-late')
        <link rel="stylesheet" href="{{ asset('assets/vendor/flatpickr/flatpickr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/dashboard-date-filters.css') }}?v={{ filemtime(public_path('assets/css/dashboard-date-filters.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/vendor/flatpickr/flatpickr.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/flatpickr/ar.js') }}"></script>
        <script src="{{ asset('assets/js/shared/dashboard-date-filters.js') }}?v={{ filemtime(public_path('assets/js/shared/dashboard-date-filters.js')) }}"></script>
    @endpush
@endonce
