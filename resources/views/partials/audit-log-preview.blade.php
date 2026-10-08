@php
    use App\Support\AuditLogLabel;
@endphp
@if(isset($audit_preview) && $audit_preview->isNotEmpty())
    @foreach($audit_preview as $log)
            <div class="audit-item">
                <span class="audit-time">{{ \App\Support\ClinicTime::formatOrNull($log->logged_at, 'Y-m-d H:i') }}</span>
                <div class="audit-desc">
                    <strong>{{ $log->user_name ?? '—' }}</strong> — {{ $log->description }}
                </div>
                <span class="audit-tag">{{ AuditLogLabel::badge($log->action, $log->tag) }}</span>
            </div>
    @endforeach
@else
    <p style="color:var(--text-muted);padding:8px 0">لا توجد حركات مسجَّلة بعد.</p>
@endif
