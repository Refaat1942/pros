<div class="panel">
    <div class="panel-header">
        <h3>📍 تتبع أوامر الشغل — الورشة</h3>
        <button type="button" class="btn-action" id="btnRefreshWorkshopTracking">↻ تحديث</button>
    </div>
    <div class="data-toolbar">
        <select id="trackingSectionFilter" class="form-control" style="max-width:220px;">
            <option value="">كل الأقسام</option>
            @foreach ($workshop_sections ?? [] as $section)
                <option value="{{ $section['id'] ?? $section->id ?? '' }}">{{ $section['name'] ?? $section->name ?? '' }}</option>
            @endforeach
        </select>
        <span class="toolbar-count" id="workshopTrackingSummary">—</span>
    </div>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>المريض</th>
                    <th>WO</th>
                    <th>القسم</th>
                    <th>الفني</th>
                    <th>مرحلة التصنيع</th>
                    <th>% إنجاز</th>
                    <th>آخر تحديث</th>
                </tr>
            </thead>
            <tbody id="workshopTrackingTable"></tbody>
        </table>
    </div>
</div>
