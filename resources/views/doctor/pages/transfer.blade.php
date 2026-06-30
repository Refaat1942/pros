@php
    $cases = collect($transferred_cases ?? []);
    $stats = $transfer_stats ?? ['total' => 0, 'spec' => 0, 'workshop' => 0, 'done' => 0];
@endphp
<div class="section-view" id="section-transfer">
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
                  <td>
                    <strong>{{ $case['name'] }}</strong>
                    @if (($case['patient_type'] ?? 'civilian') === 'military')
                      <span class="patient-type-badge military">🪖 عسكري</span>
                    @else
                      <span class="patient-type-badge civilian">🌐 مدني</span>
                    @endif
                  </td>
                  <td>{{ $case['display_entity'] ?? $case['company'] }}</td>
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
