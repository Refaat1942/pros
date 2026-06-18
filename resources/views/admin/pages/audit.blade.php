<div class="section-view" id="section-audit">
      <div id="analytics-audit">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📝', 'label' => 'عمليات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '➕', 'label' => 'إنشاء', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '✏️', 'label' => 'تحديث', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '👁️', 'label' => 'عرض', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="immutable-audit-banner">
        ⚠️ <span><strong>سجل تدقيق حصين (Immutable Audit Log):</strong> جداول «للكتابة فقط» (Append-Only). لا يملك أي مستخدم — بما في ذلك مدير الـ IT أو المدير العام — صلاحية تعديل أو حذف أي سطر. يلتقط كل حركة: المستخدم، IP/MAC، الطابع الزمني بالثانية، وقيمة البيانات قبل/بعد.</span>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3>🔒 سجل الرقابة الكامل — Immutable Audit Log</h3>
          <span class="badge">آخر ٢٤ ساعة</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="auditSearch" placeholder="🔍 بحث بالمستخدم أو الوصف...">
          <select id="auditActionFilter">
            <option value="all">كل العمليات</option>
            <option value="إنشاء">إنشاء</option>
            <option value="تحديث">تحديث</option>
            <option value="تعديل">تعديل</option>
            <option value="عرض">عرض</option>
          </select>
          <span class="toolbar-count" id="auditCount">0 حركة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportAudit('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportAudit('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body" id="auditListFull"></div>
      </div>
    </div>
