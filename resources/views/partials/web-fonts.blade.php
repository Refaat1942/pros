@if (config('assets.use_local'))
    <link rel="stylesheet" href="{{ asset('assets/css/fonts-tajawal.css') }}?v={{ filemtime(public_path('assets/css/fonts-tajawal.css')) }}">
@else
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
@endif
