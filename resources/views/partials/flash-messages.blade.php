@if (session('success'))
    <div class="flash-message flash-success" role="alert">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="flash-message flash-error" role="alert">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="flash-message flash-error" role="alert">
        <ul style="margin:0;padding-right:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
