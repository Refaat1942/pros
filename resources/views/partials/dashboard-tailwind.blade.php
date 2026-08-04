@if (config('assets.use_local'))
    <link rel="stylesheet" href="{{ asset(config('assets.tailwind_css')) }}?v={{ filemtime(public_path(config('assets.tailwind_css'))) }}">
@else
    <script src="{{ config('assets.tailwind_cdn') }}"></script>
    @stack('tailwind-theme')
@endif
