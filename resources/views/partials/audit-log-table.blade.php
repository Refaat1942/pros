<form method="GET" action="{{ route('admin.audit') }}" class="data-toolbar" style="margin-bottom:12px">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="🔍 بحث بالمستخدم أو الوصف...">
    <select name="tag">
        <option value="">كل الوسوم</option>
        @foreach($filterTags ?? [] as $tag)
            <option value="{{ $tag }}" @selected(($filters['tag'] ?? '') === $tag)>{{ $tag }}</option>
        @endforeach
    </select>
    <select name="action">
        <option value="">كل العمليات</option>
        @foreach($filterActions ?? [] as $action)
            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
        @endforeach
    </select>
    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" title="من تاريخ">
    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" title="إلى تاريخ">
    <button type="submit" class="btn-action primary">تطبيق</button>
    <span class="toolbar-count">{{ $auditLogs->total() }} حركة</span>
</form>

<div data-server-rendered="1">
    @forelse($auditLogs as $log)
        <div class="audit-item">
            <span class="audit-time">{{ $log->logged_at?->format('Y-m-d H:i:s') }}</span>
            <div class="audit-desc">
                <strong>{{ $log->user_name ?? '—' }}</strong> — {{ $log->description }}
                @if($log->ip_address)
                    <div class="audit-meta"><span>🖥️ IP: {{ $log->ip_address }}</span></div>
                @endif
            </div>
            <span class="audit-tag">{{ $log->action }} · {{ $log->tag }}</span>
        </div>
    @empty
        <p style="color:var(--text-muted);padding:12px 0">لا توجد حركات مطابقة للفلتر.</p>
    @endforelse
</div>

@if($auditLogs->hasPages())
    <div class="pagination-links" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
        @if($auditLogs->onFirstPage())
            <span class="btn-action" style="opacity:.5">السابق</span>
        @else
            <a href="{{ $auditLogs->previousPageUrl() }}" class="btn-action">السابق</a>
        @endif
        <span style="align-self:center;font-size:13px;color:var(--text-muted)">
            صفحة {{ $auditLogs->currentPage() }} / {{ $auditLogs->lastPage() }}
        </span>
        @if($auditLogs->hasMorePages())
            <a href="{{ $auditLogs->nextPageUrl() }}" class="btn-action">التالي</a>
        @else
            <span class="btn-action" style="opacity:.5">التالي</span>
        @endif
    </div>
@endif
