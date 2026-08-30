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
    @php
        $fbAppJs = public_path('assets/vendor/firebase-app-compat.js');
        $fbMsgJs = public_path('assets/vendor/firebase-messaging-compat.js');
    @endphp
    @if (is_file($fbAppJs))
        <script src="{{ asset('assets/vendor/firebase-app-compat.js') }}?v={{ filemtime($fbAppJs) }}"></script>
    @endif
    @if (is_file($fbMsgJs))
        <script src="{{ asset('assets/vendor/firebase-messaging-compat.js') }}?v={{ filemtime($fbMsgJs) }}"></script>
    @endif
    <script src="{{ asset('assets/js/shared/firebase-init.js') }}"></script>
@endif
