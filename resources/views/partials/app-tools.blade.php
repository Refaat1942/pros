{{-- أدوات عامة للمستخدم المسجَّل: طباعة عنصر مُحدَّد + المساعد الذكي (متاحة في كل اللوحات) --}}
@auth
    <link rel="stylesheet" href="{{ asset('assets/css/print-scope.css') }}?v={{ filemtime(public_path('assets/css/print-scope.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/assistant.css') }}?v={{ filemtime(public_path('assets/css/assistant.css')) }}">
    <script src="{{ asset('assets/js/shared/print-scope.js') }}?v={{ filemtime(public_path('assets/js/shared/print-scope.js')) }}"></script>
    <script>window.__ASSISTANT = { url: @json(route('assistant.search')) };</script>
    <script src="{{ asset('assets/js/shared/assistant.js') }}?v={{ filemtime(public_path('assets/js/shared/assistant.js')) }}"></script>
    <script>
        (function () {
            var minutes = @json((int) config('session.idle_timeout', 5));
            var ms = Math.max(1, minutes) * 60 * 1000;
            var timer;

            function logoutIdle() {
                var form = document.querySelector('.sidebar-logout-form');
                if (form) {
                    form.submit();
                    return;
                }
                window.location.href = @json(route('home'));
            }

            function resetIdleTimer() {
                clearTimeout(timer);
                timer = setTimeout(logoutIdle, ms);
            }

            ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (eventName) {
                document.addEventListener(eventName, resetIdleTimer, { passive: true });
            });

            resetIdleTimer();
        })();
    </script>
@endauth
