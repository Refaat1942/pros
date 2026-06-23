@php
    $records = collect($medical_records ?? []);
@endphp
<div class="section-view" id="section-records">
      <div class="panel">
        <div class="panel-header">
          <h3>📁 السجل الطبي — التقارير المعتمدة</h3>
          <span class="count-badge" id="recordsHeaderCount">{{ $records->count() }} تقرير</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="recordsSearch" placeholder="🔍 بحث بالاسم أو الهاتف أو التشخيص...">
          <span class="toolbar-count" id="recordsCount">{{ $records->count() }} تقرير</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportRecords('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportRecords('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>المريض</th>
                <th>رقم الهاتف</th>
                <th>التشخيص</th>
                <th>الطبيب</th>
                <th>التاريخ</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="recordsTable" data-server-rendered="1">
              @forelse ($records as $record)
                @php
                    $summary = \Illuminate\Support\Str::limit($record['diagnosis'] ?? '—', 80);
                    $recordDate = ! empty($record['record_date'])
                        ? \Illuminate\Support\Carbon::parse($record['record_date'])->format('d/m/Y')
                        : '—';
                @endphp
                <tr data-record-id="{{ $record['id'] }}">
                  <td><strong>{{ $record['patient_name'] }}</strong></td>
                  <td style="font-size:12px;color:var(--text-muted);direction:ltr;text-align:right;">{{ $record['phone'] ?? '—' }}</td>
                  <td><div class="rec-list"><span>{{ $summary }}</span></div></td>
                  <td>{{ $record['doctor_name'] ?? '—' }}</td>
                  <td>{{ $recordDate }}</td>
                  <td>
                    <button type="button"
                            class="btn btn-secondary btn-record-view"
                            style="padding:6px 12px;font-size:12px;"
                            data-record-id="{{ $record['id'] }}"
                            data-record='@json($record, JSON_HEX_APOS | JSON_HEX_QUOT)'>
                      عرض
                    </button>
                  </td>
                </tr>
              @empty
                <tr class="pagination-empty-row">
                  <td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">
                    لا توجد تقارير معتمدة بعد — سيظهر المريض هنا بعد حفظ واعتماد التشخيص.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
<script>
window.__DOCTOR_CONFIG = { militaryEntity: @json(\App\Models\Patient::MILITARY_SOVEREIGN_ENTITY) };
window.__MEDICAL_RECORDS = @json($records->values());
</script>
