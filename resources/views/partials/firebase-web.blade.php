@php
    $fb = config('firebase.web');
    $fbEnabled = (bool) config('firebase.enabled')
        && ! empty($fb['apiKey'])
        && ! empty($fb['appId'])
        && ! empty($fb['messagingSenderId']);
@endphp
<script>
    window.FIREBASE_WEB = @json($fbEnabled ? $fb : null);
</script>
@if ($fbEnabled)
    <script src="{{ asset('assets/vendor/firebase-app-compat.js') }}?v={{ filemtime(public_path('assets/vendor/firebase-app-compat.js')) }}"></script>
    <script src="{{ asset('assets/vendor/firebase-messaging-compat.js') }}?v={{ filemtime(public_path('assets/vendor/firebase-messaging-compat.js')) }}"></script>
    <script src="{{ asset('assets/js/shared/firebase-init.js') }}"></script>
@endif
