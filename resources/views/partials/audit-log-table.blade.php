<form method="GET" action="{{ route('admin.audit') }}" class="data-toolbar" style="margin-bottom:12px">
    @php use App\Support\AuditLogLabel; @endphp
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="🔍 بحث بالمستخدم أو الوصف...">
    <select name="tag">
        <option value="">كل الوسوم</option>
        @foreach($filterTags ?? [] as $tag)
            <option value="{{ $tag }}" @selected(($filters['tag'] ?? '') === $tag)>{{ AuditLogLabel::tag($tag) }}</option>
        @endforeach
    </select>
    <select name="action">
        <option value="">كل العمليات</option>
        @foreach($filterActions ?? [] as $action)
            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ AuditLogLabel::action($action) }}</option>
        @endforeach
    </select>
    <div class="date-filter-group">
        <label class="date-filter">
            <span class="date-filter-lbl">من تاريخ</span>
            <span class="date-filter-field">
                <input type="text"
                       name="date_from"
                       id="auditFilterDateFrom"
                       value="{{ $filters['date_from'] ?? '' }}"
                       placeholder="YYYY-MM-DD"
                       dir="ltr"
                       class="date-filter-input"
                       pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}"
                       maxlength="10"
                       autocomplete="off"
                       inputmode="numeric">
                <button type="button" class="date-filter-picker" data-target="auditFilterDateFrom" title="اختر من التقويم" aria-label="اختر من التقويم">📅</button>
                <input type="date" class="date-filter-native" tabindex="-1" aria-hidden="true">
            </span>
        </label>
        <label class="date-filter">
            <span class="date-filter-lbl">إلى تاريخ</span>
            <span class="date-filter-field">
                <input type="text"
                       name="date_to"
                       id="auditFilterDateTo"
                       value="{{ $filters['date_to'] ?? '' }}"
                       placeholder="YYYY-MM-DD"
                       dir="ltr"
                       class="date-filter-input"
                       pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}"
                       maxlength="10"
                       autocomplete="off"
                       inputmode="numeric">
                <button type="button" class="date-filter-picker" data-target="auditFilterDateTo" title="اختر من التقويم" aria-label="اختر من التقويم">📅</button>
                <input type="date" class="date-filter-native" tabindex="-1" aria-hidden="true">
            </span>
        </label>
    </div>
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
            <span class="audit-tag">{{ AuditLogLabel::badge($log->action, $log->tag) }}</span>
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

<script>
(function () {
    document.querySelectorAll('.date-filter-picker').forEach(function (btn) {
        var wrap = btn.closest('.date-filter-field');
        var text = document.getElementById(btn.getAttribute('data-target'));
        var native = wrap ? wrap.querySelector('.date-filter-native') : null;
        if (!text || !native) return;

        if (text.value) native.value = text.value;

        btn.addEventListener('click', function () {
            if (text.value) native.value = text.value;
            if (typeof native.showPicker === 'function') native.showPicker();
            else native.click();
        });

        native.addEventListener('change', function () {
            text.value = native.value;
        });
    });
})();
</script>
