@php
    $cases = collect($transferred_cases ?? []);
    $stats = $transfer_stats ?? ['total' => 0, 'spec' => 0, 'workshop' => 0, 'done' => 0];
@endphp
<div class="section-view" id="section-transfer">
      <div id="analytics-transfer">@include('partials.dashboard-analytics-empty', [
        'hide_charts' => true,
        'stats' => [
        ['icon' => '🔧', 'label' => 'محول', 'value' => (string) ($stats['total'] ?? 0), 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '⚙️', 'label' => 'قيد التوصيف', 'value' => (string) ($stats['spec'] ?? 0), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '🏭', 'label' => 'في الورشة', 'value' => (string) ($stats['workshop'] ?? 0), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '✅', 'label' => 'مكتمل', 'value' => (string) ($stats['done'] ?? 0), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📦 الحالات المحولة للتوصيف</h3>
          <span class="count-badge" id="transferredCount">{{ $stats['total'] ?? 0 }}</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="transferSearch" placeholder="🔍 بحث بالاسم أو الجهة...">
          <select id="transferStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="قيد التوصيف">قيد التوصيف</option>
            <option value="في الورشة">في الورشة</option>
            <option value="مكتمل">مكتمل</option>
          </select>
          <span class="toolbar-count" id="transferCount">{{ $stats['total'] ?? 0 }} حالة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportTransferred('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportTransferred('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المريض</th>
                <th>الجهة</th>
                <th>تاريخ التحويل</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="transferredTable" data-server-rendered="1">
              @forelse ($cases as $case)
                <tr class="record-row-clickable"
                    data-transfer-id="{{ $case['id'] }}"
                    data-search="{{ $case['name'] }} {{ $case['company'] }} {{ $case['status'] }}"
                    title="عرض التفاصيل">
                  <td><strong>{{ $case['name'] }}</strong></td>
                  <td>{{ $case['company'] }}</td>
                  <td>{{ $case['date'] }}</td>
                  <td><span class="priority-badge normal">{{ $case['status'] }}</span></td>
                </tr>
              @empty
                <tr class="pagination-empty-row">
                  <td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">
                    لا توجد حالات محوّلة بعد — تظهر هنا بعد اعتماد التشخيص وتحويل الحالة للتوصيف الفني.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
<script>
window.__TRANSFERRED_CASES = @json($cases->values());
</script>
