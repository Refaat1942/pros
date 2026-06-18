<div class="section-view" id="section-spec">
      <div id="analytics-spec">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📤', 'label' => 'عمليات صرف', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '📋', 'label' => 'للتسعير', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🔩', 'label' => 'متوسط البنود', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '⏱️', 'label' => 'متوسط الوقت', 'value' => '—', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📦 معاينة التوصيف — بدون صرف مخزني</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">هذا القسم للمعاينة فقط — الصرف الفعلي يتم من <strong>لوحة المخزون (BOM)</strong> بعد موافقة العميل.</p>
          <ul class="order-list" id="ordersListSpec" style="margin-bottom:20px;border:1px solid var(--border);border-radius:10px;"></ul>
          <div id="specSectionHint" class="empty-state" style="padding:24px;">
            <div class="icon">📦</div>
            <p>اختر طلب صرف لعرض تفاصيل الأصناف المطلوبة</p>
          </div>
        </div>
      </div>
    </div>
