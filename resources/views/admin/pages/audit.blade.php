<div class="section-view" id="section-audit">
      <div id="analytics-audit">
        @isset($audit_stats)
          @include('partials.dashboard-analytics-empty', ['stats' => $audit_stats, 'hide_charts' => true])
        @else
          @include('partials.dashboard-analytics-empty', ['stats' => [
            ['icon' => '📝', 'label' => 'عمليات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
            ['icon' => '➕', 'label' => 'إنشاء', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
            ['icon' => '✏️', 'label' => 'تحديث', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ['icon' => '👁️', 'label' => 'عرض', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
          ], 'hide_charts' => true])
        @endisset
      </div>
      <div class="immutable-audit-banner">
        ⚠️ <span><strong>سجل تدقيق حصين (Immutable Audit Log):</strong> جداول «للكتابة فقط» (Append-Only). لا يملك أي مستخدم — بما في ذلك مدير الـ IT أو المدير العام — صلاحية تعديل أو حذف أي سطر. يلتقط كل حركة: المستخدم، IP/MAC، الطابع الزمني بالثانية، وقيمة البيانات قبل/بعد.</span>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3>🔒 سجل الرقابة الكامل — Immutable Audit Log</h3>
          <span class="badge">للقراءة فقط</span>
        </div>
        <div class="panel-body" id="auditListFull" @isset($auditLogs) data-server-rendered="1" data-audit-total="{{ $auditLogs->total() }}" @endisset>
          @isset($auditLogs)
            @include('partials.audit-log-table')
          @else
            <p style="color:var(--text-muted)">لا توجد بيانات.</p>
          @endisset
        </div>
      </div>
    </div>
