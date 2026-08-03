@php
    $fb = config('firebase.web');
    $fbEnabled = ! empty($fb['apiKey']) && ! empty($fb['appId']) && ! empty($fb['messagingSenderId']);
@endphp
<script>
    window.FIREBASE_WEB = @json($fbEnabled ? $fb : null);
</script>
@if ($fbEnabled)
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js"></script>
    <script src="{{ asset('assets/js/shared/firebase-init.js') }}"></script>
@endif
