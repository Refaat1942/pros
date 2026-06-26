@php
    $reports = $admin_reports ?? [];
    $financial = $reports['financial'] ?? [];
    $inventory = $reports['inventory'] ?? [];
    $operations = $reports['operations'] ?? [];
    $bom = $reports['bom'] ?? [];
    $bomSummary = $bom['summary'] ?? [];
    $bomRows = $bom['rows'] ?? [];
    $stageBadge = [
        'raw'      => 'ops-badge ops-badge--raw',
        'wip'      => 'ops-badge ops-badge--wip',
        'finished' => 'ops-badge ops-badge--done',
    ];
@endphp
<div class="section-view" id="section-reports" data-server-rendered="1">
      <div class="reports-section-title">💰 التقارير المالية والتشغيلية</div>
      <div class="report-cards report-cards--financial" id="financialReportCards">
        <div class="report-card">
          <h4>📈 الإيرادات الشهرية — {{ $financial['month_label'] ?? '' }}</h4>
          <div style="font-size:28px;font-weight:800;color:#059669;margin:8px 0;">
            {{ number_format((float) ($financial['monthly_revenue'] ?? 0), 2) }} ج.م
          </div>
          <p style="font-size:12px;color:var(--text-muted);margin:0;">
            {{ (int) ($financial['delivered_count'] ?? 0) }} حالة مدنية مُسلّمة هذا الشهر
          </p>
        </div>

        <div class="report-card">
          <h4>🔥 الأصناف الأكثر طلباً (BOM)</h4>
          @forelse ($financial['top_items'] ?? [] as $item)
            <div class="report-bar">
              <span style="min-width:72px;font-weight:600;">{{ $item['code'] }}</span>
              <div class="bar-track">
                @php $maxQty = max(1, collect($financial['top_items'] ?? [])->max('qty') ?: 1); @endphp
                <div class="bar-fill" style="width:{{ min(100, round(($item['qty'] / $maxQty) * 100)) }}%"></div>
              </div>
              <strong>{{ $item['qty'] }}</strong>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin:-6px 0 8px 82px;">{{ $item['name'] }}</div>
          @empty
            <p style="color:var(--text-muted);font-size:13px;">لا توجد بنود BOM مسجّلة بعد.</p>
          @endforelse
        </div>

        <div class="report-card">
          <h4>📋 أوامر التشغيل — هذا الشهر</h4>
          <div style="font-size:24px;font-weight:800;color:#0e7490;margin-bottom:10px;">
            {{ (int) ($financial['work_orders_count'] ?? 0) }} أمر
          </div>
          @forelse (array_slice($financial['work_orders'] ?? [], 0, 5) as $wo)
            <div class="stagnant-item">
              <span><strong>{{ $wo['work_order_no'] }}</strong> — {{ $wo['patient'] }}</span>
            </div>
          @empty
            <p style="color:var(--text-muted);font-size:13px;">لا توجد أوامر هذا الشهر.</p>
          @endforelse
        </div>
      </div>

      <div class="reports-section-title">📦 تقارير المخزون والتحليلات الذكية</div>
      <div class="report-cards report-cards--inventory" id="inventoryReportCards">
        <div class="report-card report-card--health">
          <h4>💚 صحة المخزون الإجمالية</h4>
          <div class="health-score-wrap">
            <div style="font-size:36px;font-weight:800;color:{{ ($inventory['health_pct'] ?? 0) >= 70 ? '#059669' : '#d97706' }};">
              {{ (int) ($inventory['health_pct'] ?? 0) }}%
            </div>
            <div style="font-size:13px;color:var(--text-muted);line-height:1.6;">
              {{ (int) ($inventory['item_count'] ?? 0) }} صنف —
              {{ (int) ($inventory['low_stock'] ?? 0) }} منخفض —
              قيمة WAC: <strong>{{ number_format((float) ($inventory['total_value'] ?? 0), 2) }} ج.م</strong>
            </div>
          </div>
        </div>

        <div class="report-card">
          <h4>⚠️ الأصناف الراكدة (180+ يوم)</h4>
          @forelse (array_slice($inventory['stagnant_items'] ?? [], 0, 6) as $item)
            <div class="stagnant-item">
              <span>{{ $item['code'] }} — {{ $item['name'] }}</span>
              <span style="color:var(--text-muted);">{{ $item['qty'] }} · {{ $item['last_moved_at'] ?? '—' }}</span>
            </div>
          @empty
            <p style="color:var(--text-muted);font-size:13px;">لا توجد أصناف راكدة.</p>
          @endforelse
        </div>

        <div class="report-card">
          <h4>🔴 تحت الحد الأدنى</h4>
          @forelse ($inventory['low_stock_items'] ?? [] as $item)
            <div class="stagnant-item">
              <span>{{ $item['code'] }}</span>
              <strong style="color:#dc2626;">{{ $item['qty'] }}</strong>
            </div>
          @empty
            <p style="color:var(--text-muted);font-size:13px;">كل الأصناف فوق الحد.</p>
          @endforelse
        </div>

        <div class="report-card report-card--issues">
          <h4>📤 حركات الصرف — هذا الشهر</h4>
          <div style="font-size:28px;font-weight:800;color:#0e7490;">
            {{ number_format((int) ($inventory['issues_this_month'] ?? 0)) }} وحدة
          </div>
        </div>

        <div class="report-card report-card--batch">
          <h4>🏷️ الدفعات النشطة (Batch Tracking)</h4>
          <div class="batch-total">{{ (int) ($inventory['active_batches'] ?? 0) }} دفعة</div>
          <div class="batch-samples-grid">
          @forelse ($inventory['batch_samples'] ?? [] as $batch)
            <div class="batch-sample-chip">
              <strong>{{ $batch['code'] }}</strong>
              <span>{{ number_format($batch['amount'], 2) }} ج.م × {{ $batch['qty'] }}</span>
            </div>
          @empty
            <p style="color:var(--text-muted);font-size:13px;grid-column:1/-1;">لا توجد دفعات نشطة.</p>
          @endforelse
          </div>
        </div>

        <div class="reports-bom-row">
        <div class="report-card report-card--bom" id="bomAdminPanel">
          <h4>📋 BOM — خام / تحت التشغيل / تام (قيمة Highest Batch Cost)</h4>
          <div id="bomAdminSummary" class="bom-admin-summary">
            @foreach (['raw' => 'خام', 'wip' => 'تحت التشغيل', 'finished' => 'تام'] as $key => $label)
              @php $stat = $bomSummary[$key] ?? ['count' => 0, 'value' => 0, 'lines' => 0]; @endphp
              <div class="bom-admin-stat {{ $key === 'finished' ? 'finished' : $key }}">
                <div class="bas-label">{{ $label }}</div>
                <div class="bas-value">{{ (int) $stat['count'] }} قائمة</div>
                <div class="bas-money">{{ number_format((float) $stat['value'], 2) }} ج.م</div>
                <div class="bas-sub">{{ (int) $stat['lines'] }} بند</div>
              </div>
            @endforeach
          </div>
          <div class="bom-admin-table-wrap">
            <table data-paginate="10" class="data-table bom-admin-table">
              <thead>
                <tr>
                  <th>المريض</th>
                  <th>أمر التشغيل</th>
                  <th>المرحلة</th>
                  <th>البنود</th>
                  <th>قيمة BOM</th>
                </tr>
              </thead>
              <tbody id="bomAdminTable">
                @forelse ($bomRows as $row)
                  <tr>
                    <td><strong>{{ $row['patient'] }}</strong></td>
                    <td><span class="ops-wo">{{ $row['work_order_no'] }}</span></td>
                    <td><span class="{{ $stageBadge[$row['stage']] ?? 'ops-badge' }}">{{ $row['stage_label'] }}</span></td>
                    <td style="text-align:center;font-weight:700;">{{ $row['line_count'] }}</td>
                    <td style="font-weight:700;">{{ number_format($row['value'], 2) }} ج.م</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="empty-cell">لا توجد قوائم BOM</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="report-card report-card--fill" id="pendingPrepPanel">
          <h4>⏳ أوامر تحضير معلقة</h4>
          <div class="pending-prep-stats">
            <div class="pending-prep-stat pending-prep-stat--main">
              <span class="pending-prep-stat__value">{{ (int) ($operations['awaiting_dispense'] ?? 0) }}</span>
              <span class="pending-prep-stat__label">أمر بانتظار الصرف</span>
              <p class="pending-prep-stat__hint">BOM «خام» — بانتظار صرف المخزن</p>
            </div>
            <div class="pending-prep-stat">
              <span class="pending-prep-stat__value">{{ (int) ($operations['in_workshop'] ?? 0) }}</span>
              <span class="pending-prep-stat__label">🏭 تحت التشغيل</span>
            </div>
            <div class="pending-prep-stat">
              <span class="pending-prep-stat__value">{{ (int) ($operations['ready_for_delivery'] ?? 0) }}</span>
              <span class="pending-prep-stat__label">✅ جاهز للتسليم</span>
            </div>
            <div class="pending-prep-stat">
              <span class="pending-prep-stat__value">{{ (int) ($operations['open_work_orders'] ?? 0) }}</span>
              <span class="pending-prep-stat__label">🎯 أوامر نشطة</span>
            </div>
          </div>
        </div>
        </div>
      </div>
    </div>
