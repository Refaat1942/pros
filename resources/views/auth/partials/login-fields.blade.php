@if ($errors->any())
    <div class="auth-alert" role="alert" aria-live="assertive">
        <span class="auth-alert__icon" aria-hidden="true">⚠️</span>
        <div class="auth-alert__body">
            @foreach (array_unique($errors->all()) as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    </div>
@endif

<div class="form-group">
    <label for="username">اسم المستخدم</label>
    <input
        type="text"
        id="username"
        name="username"
        value="{{ old('username') }}"
        autocomplete="username"
        class="{{ $errors->has('username') ? 'is-invalid' : '' }}"
        @if ($errors->has('username')) aria-invalid="true" @endif
        autofocus
    >
</div>

<div class="form-group">
    <label for="password">كلمة المرور</label>
    <div class="password-field">
        <input
            type="password"
            id="password"
            name="password"
            placeholder="••••••••"
            autocomplete="current-password"
            class="{{ $errors->any() ? 'is-invalid' : '' }}"
            @if ($errors->any()) aria-invalid="true" @endif
        >
        <button type="button" class="password-toggle" id="passwordToggle"
                aria-controls="password" aria-pressed="false" aria-label="إظهار كلمة المرور" title="إظهار كلمة المرور">
            <svg class="password-toggle__show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="password-toggle__hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a19.77 19.77 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A10.7 10.7 0 0 1 12 4c6.5 0 10 7 10 7a19.9 19.9 0 0 1-3.17 4.19"/>
                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                <path d="M1 1l22 22"/>
            </svg>
        </button>
    </div>
</div>
