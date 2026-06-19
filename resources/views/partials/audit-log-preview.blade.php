@if(isset($audit_preview) && $audit_preview->isNotEmpty())
    <div data-server-rendered="1">
        @foreach($audit_preview as $log)
            <div class="audit-item">
                <span class="audit-time">{{ $log->logged_at?->format('Y-m-d H:i') }}</span>
                <div class="audit-desc">
                    <strong>{{ $log->user_name ?? '—' }}</strong> — {{ $log->description }}
                </div>
                <span class="audit-tag">{{ $log->action }} · {{ $log->tag }}</span>
            </div>
        @endforeach
    </div>
@else
    <p style="color:var(--text-muted);padding:8px 0">لا توجد حركات مسجَّلة بعد.</p>
@endif
